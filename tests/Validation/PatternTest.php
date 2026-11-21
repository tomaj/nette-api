<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Validation;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Input\SchemaBuilder;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Params\PathValues;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;

final class PatternTest extends TestCase
{
    protected function setUp(): void
    {
        $this->clean();
    }

    protected function tearDown(): void
    {
        $this->clean();
    }

    public function testPatternsAreExportedOnlyWhenPortable(): void
    {
        $this->assertSame('^[a-z]+$', new Pattern('{^[a-z]+$}')->expression());
        $this->assertSame('^[a-z]+$', new Pattern('(^[a-z]+$)')->expression());
        $this->assertSame('^[a-z]+$', new Pattern('[^[a-z]+$]')->expression());
        $this->assertSame('^[a-z]+$', new Pattern('<^[a-z]+$>')->expression());
        $this->assertSame('^[a-z]+$', new Pattern('#^[a-z]+$#uD')->expression());
        $this->assertNull(new Pattern('{^[a-z]+$}i')->expression());
        $this->assertNull(new Pattern('/^[a-z]+$/i')->expression());
        $this->assertNull(new Pattern('/^[a-z]+$/x')->expression());
    }

    /**
     * @return \Iterator<string, array{string, ?string}>
     */
    public static function ecmaCompatiblePatterns(): \Iterator
    {
        yield 'plain' => ['/^[a-z]+$/', '^[a-z]+$'];
        yield 'escaped plus' => ['/^a\+\+$/', '^a\+\+$'];
        yield 'lookahead and a group' => ['/^(?=a)(?:a|b)+x$/u', '^(?=a)(?:a|b)+x$'];
        yield 'lookahead and named group' => ['/^(?=a)(?<n>a|b)+x$/u', null];
        yield 'start anchor' => ['/\Aab\z/', null];
        yield 'end anchor Z' => ['/ab\Z/', null];
        yield 'possessive' => ['/^a++$/', null];
        yield 'possessive braces' => ['/^a{2}+$/', null];
        yield 'possessive question' => ['/^a?+$/', null];
        yield 'atomic group' => ['/^(?>a+)b$/', null];
        yield 'python group' => ['/(?P<n>a)/', null];
        yield 'inline flag' => ['/(?i)a/', null];
        yield 'scoped flag' => ['/(?i:a)b/', null];
        yield 'horizontal space' => ['/a\hb/', null];
    }

    #[DataProvider('ecmaCompatiblePatterns')]
    public function testOnlyEcmaCompatiblePatternsReachOpenApi(string $pattern, ?string $expected): void
    {
        $this->assertSame($expected, new Pattern($pattern)->expression());
    }

    /**
     * @return \Iterator<string, array{string, string|null}>
     */
    public static function pcreOnlyConstructs(): \Iterator
    {
        yield 'unicode property with a quantifier' => ['/^\p{L}+$/u', null];
        yield 'braces after an escaped brace' => ['/\{3}+/', null];
        yield 'escaped backslash and A' => ['/\\\\A/', '\\\\A'];
        yield 'escaped backslash and a letter that is only PCRE after one more backslash' => ['/a\\\\\\\\A/', 'a\\\\\\\\A'];
        yield 'escaped plus then a quantifier' => ['/a\++/', 'a\++'];
        yield 'lazy quantifier' => ['/[0-9]+?/', '[0-9]+?'];
        yield 'lookbehind' => ['/(?<=a)b/', null];
        yield 'named group' => ['/(?<name>a)/', null];
        yield 'hex code point' => ['/^\x{41}+$/u', null];
        yield 'hex byte' => ['/^\x41+$/', null];
        yield 'posix class' => ['/[[:alpha:]]/', null];
        yield 'comment' => ['/a(?#note)b/', null];
        yield 'branch reset' => ['/(?|a|b)/', null];
        yield 'escape character' => ['/a\e/', null];
        yield 'back reference g' => ['/(a)\g{1}/', null];
        yield 'escaped backslash then possessive' => ['/\\\\++/', null];
        yield 'three backslashes then A' => ['/\\\\\A/', null];
        yield 'possessive after a number of repetitions' => ['/a{2}+/', null];
        yield 'possessive after a range' => ['/[a-z]{2,}+/', null];
        yield 'relative recursion' => ['/(a)(?-1)/', null];
        yield 'numbered recursion' => ['/(a)(?1)/', null];
        yield 'named recursion' => ['/(?<n>a)(?&n)/', null];
        yield 'single quoted group name' => ["/(?'n'a)/", null];
        yield 'non newline escape' => ['/a\N/', null];
        yield 'possessive question' => ['/a?+/', null];
        yield 'possessive star' => ['/a*+/', null];
    }

    #[DataProvider('pcreOnlyConstructs')]
    public function testPatternOmissionFromOpenApi(string $pattern, ?string $expression): void
    {
        $this->assertSame($expression, new Pattern($pattern)->expression());
    }

    /**
     * @return \Iterator<string, array{string, bool}>
     */
    public static function portability(): \Iterator
    {
        yield 'zip code' => ['/^[0-9]{5}$/', true];
        yield 'with the flags u and D' => ['/^[a-z]+$/uD', true];
        yield 'other delimiters' => ['#^[a-z]+$#', true];
        yield 'brackets as delimiters' => ['{^[a-z]+$}', true];
        yield 'alternatives' => ['/^(?:ab|cd)$/', true];
        yield 'alternative at the top' => ['/^a|b$/', true];
        yield 'empty alternative' => ['/^(a|)$/', true];
        yield 'empty group' => ['/^()$/', true];
        yield 'a capturing group' => ['/^(ab)+$/', true];
        yield 'lookahead' => ['/^(?=a)a$/', true];
        yield 'negative lookahead' => ['/^(?!a)b$/', true];
        yield 'greedy and lazy quantifiers' => ['/^a*b+?c??d{2}e{2,}f{2,3}?$/', true];
        yield 'quantified group' => ['/^(?:a|b){2,3}$/', true];
        yield 'escaped punctuation' => ['/^\\\\\^\$\.\*\+\?\(\)\[\]\{\}\|\/$/', true];
        yield 'escaped whitespace' => ['/^\n\r\t$/', true];
        yield 'class with literals and a range' => ['/^[A-Za-z0-9._%+-]+$/', true];
        yield 'class with escapes' => ['/^[\-\]\\\\\n\.\^]+$/', true];
        yield 'dash at the start of a class' => ['/^[-a]+$/', true];
        yield 'caret inside a class' => ['/^[a^]+$/', true];
        yield 'space and punctuation' => ['/^a b,c;d:e=f#g%h&i-j@k!l~m"n\'o<p>q`r_s$/', true];
        yield 'digits and word characters without u' => ['/^\w+\d\b$/', true];
        yield 'word boundary' => ['/^\ba\b$/', true];
        yield 'nested groups' => ['/^((a)(b))$/', true];

        yield 'digits with u' => ['/^\d+$/u', false];
        yield 'word characters with u' => ['/^\w+$/u', false];
        yield 'word boundary with u' => ['/^\ba$/u', false];
        yield 'digits with u and D' => ['/^\d$/uD', false];
        yield 'the dot' => ['/^a.b$/', false];
        yield 'negated class' => ['/^[^a]$/', false];
        yield 'whitespace class' => ['/^\s+$/', false];
        yield 'negated digit' => ['/^\D+$/', false];
        yield 'negated word' => ['/^\W+$/', false];
        yield 'negated boundary' => ['/^\B$/', false];
        yield 'unicode property' => ['/^\p{L}+$/u', false];
        yield 'short unicode property' => ['/^\pL+$/u', false];
        yield 'hex escape' => ['/^\x41$/', false];
        yield 'vertical whitespace' => ['/^\v$/', false];
        yield 'back reference' => ['/^(a)\1$/', false];
        yield 'escaped dash outside a class' => ['/^\-$/', false];
        yield 'escaped at sign' => ['/^a\@$/', false];
        yield 'escaped underscore' => ['/^\_$/', false];
        yield 'escaped delimiter other than slash' => ['#^\#$#', false];
        yield 'class escape other than punctuation' => ['/^[\d]$/', false];
        yield 'class with the closing bracket first' => ['/^[]a]$/', false];
        yield 'empty negated class' => ['/^[^]a]$/', false];
        yield 'a bracket inside a class' => ['/^[[]$/', false];
        yield 'unescaped closing bracket' => ['/^a]$/', false];
        yield 'unescaped closing brace' => ['/^a}$/', false];
        yield 'unescaped opening brace' => ['/^a{$/', false];
        yield 'quantifier without a minimum' => ['/^a{,3}$/', false];
        yield 'double quantifier' => ['/^a{2}{3}$/', false];
        yield 'quantifier after an anchor' => ['/^?a$/', false];
        yield 'possessive' => ['/^a++$/', false];
        yield 'quantified lookahead' => ['/^(?=a)*a$/', false];
        yield 'quantified word boundary' => ['/^\b+a$/', false];
        yield 'named group' => ['/^(?<n>a)$/', false];
        yield 'lookbehind' => ['/(?<=a)b/', false];
        yield 'atomic group' => ['/^(?>a)$/', false];
        yield 'inline flag' => ['/^(?i)a$/', false];
        yield 'case insensitive flag' => ['/^a$/i', false];
        yield 'multiline flag' => ['/^a$/m', false];
        yield 'extended flag' => ['/^a$/x', false];
        yield 'dot all flag' => ['/^a$/s', false];
        yield 'a non ASCII letter' => ['/^é$/', false];
        yield 'a non ASCII letter in a class' => ['/^[é]$/u', false];
        yield 'a control character' => ["/^a\tb\$/", false];
        yield 'an unclosed group is not valid anyway but a stray bracket is' => ['/^(a))$/', false];
        yield 'start of subject anchor' => ['/\Aa\z/', false];
    }

    #[DataProvider('portability')]
    public function testOnlyAPatternThatMeansTheSameInEcma262IsExported(string $pattern, bool $portable): void
    {
        $expression = null;
        try {
            $expression = new Pattern($pattern)->expression();
        } catch (\InvalidArgumentException) {
            $this->assertFalse($portable, $pattern);

            return;
        }

        $this->assertSame($portable ? substr($pattern, 1, strrpos($pattern, $pattern[0] === '{' ? '}' : $pattern[0]) - 1) : null, $expression, $pattern);
    }

    public function testAPortablePatternIsDocumentedAsPatternAndTheOthersAsXPattern(): void
    {
        $fields = DtoCompiler::compile(PatternHandler::class)->fields;

        $this->assertSame(['type' => 'string', 'pattern' => '^[0-9]{5}$'], SchemaBuilder::build($fields[0], true));
        $this->assertSame(['type' => 'string', 'x-pattern' => '/^\p{Lu}[a-z]+$/u'], SchemaBuilder::build($fields[1], true));
        $this->assertSame(['type' => 'string', 'x-pattern' => '/^[a-z]+$/i'], SchemaBuilder::build($fields[2], true));
        $this->assertSame(['type' => 'string', 'minLength' => 2, 'pattern' => '^aa$'], SchemaBuilder::build($fields[3], true));
    }

    public function testThePatternIsStillCheckedWhateverIsExported(): void
    {
        $_GET = ['name' => 'ada'];

        $this->assertSame(['name' => ['Value must match the pattern /^\p{Lu}[a-z]+$/u']], $this->errors(new NameHandler()));
    }

    private function clean(): void
    {
        RequestInput::reset();
        PathValues::reset();
        $_GET = [];
        $_POST = [];
        unset($_SERVER['HTTP_X_T'], $_SERVER['CONTENT_TYPE'], $_SERVER['REQUEST_METHOD']);
    }

    /**
     * @return array<string, list<string>>
     */
    private function errors(TypedHandler $handler): array
    {
        $processor = new ParamsProcessor($handler->params());
        $this->assertTrue($processor->isError());

        return $processor->getErrors();
    }
}

final readonly class PatternInput
{
    public function __construct(
        #[Query] #[Pattern('/^[0-9]{5}$/')] public string $zip,
        #[Query] #[Pattern('/^\p{Lu}[a-z]+$/u')] public string $name,
        #[Query] #[Pattern('/^[a-z]+$/i')] public string $code,
        #[Query] #[Length(min: 2)] #[Pattern('/^aa$/')] public string $both,
    ) {
    }
}

final class PatternHandler extends TypedHandler
{
    public function handleDto(PatternInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class NameInput
{
    public function __construct(#[Query] #[Pattern('/^\p{Lu}[a-z]+$/u')] public string $name)
    {
    }
}

final class NameHandler extends TypedHandler
{
    public function handleDto(NameInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}
