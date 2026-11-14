<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use FooEval\Bar;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\File;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\TypeName;
use Tomaj\NetteApi\Test\Input\Fixtures\ImportScope;
use Tomaj\NetteApi\Test\Input\Fixtures\SecondScope;
use Tomaj\NetteApi\Validation\Email;
use Tomaj\NetteApi\Validation\Range;

final class TypeNameTest extends TestCase
{
    public function testResolvesLikePhp(): void
    {
        $scope = new \ReflectionClass(ImportScope::class);
        $this->assertSame('string', TypeName::resolve(' string ', $scope));
        $this->assertSame('string', TypeName::resolve('\\string', $scope));
        $this->assertSame('int', TypeName::resolve('Int', $scope));
        // `use ... as RangeAlias ;` with a space before the semicolon is still an import
        $this->assertSame(Range::class, TypeName::resolve(' RangeAlias ', $scope));
        $this->assertSame(Range::class, TypeName::resolve('rangealias', $scope));
        // a leading backslash is fully qualified, imports do not apply
        $this->assertSame('RangeAlias', TypeName::resolve('\\RangeAlias', $scope));
        $this->assertSame(\DateTimeImmutable::class, TypeName::resolve('\\DateTimeImmutable', $scope));
        $this->assertSame(Email::class, TypeName::resolve('EmailAlias', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\Email', TypeName::resolve('Email', $scope));
        $this->assertSame(Query::class, TypeName::resolve('Q', $scope));
        $this->assertSame(Body::class, TypeName::resolve('Body', $scope));
        $this->assertSame(File::class, TypeName::resolve('File', $scope));
        $this->assertSame('Myfunction\\Widget', TypeName::resolve('Widget', $scope));
        $this->assertSame('Myfunction\\Widget\\Part', TypeName::resolve('Widget\\Part', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Validation\\Sub\\Thing', TypeName::resolve('Validation\\Sub\\Thing', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\Other\\Thing', TypeName::resolve('Other\\Thing', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\Ghost', TypeName::resolve('Ghost', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\strlen', TypeName::resolve('strlen', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\Trait1', TypeName::resolve('Trait1', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\Captured', TypeName::resolve('Captured', $scope));
        eval('namespace FooEval { class Bar {} }');
        $this->assertSame('FooEval\\Widget', TypeName::resolve('Widget', new \ReflectionClass(Bar::class)));
    }

    public function testImportsBelongToTheNamespaceBlockOfTheClass(): void
    {
        $scope = new \ReflectionClass(SecondScope::class);
        $this->assertSame('Second\\Imported\\Widget', TypeName::resolve('Widget', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\EmailAlias', TypeName::resolve('EmailAlias', $scope));
    }

    public function testImportsOfCode(): void
    {
        $code = <<<'PHP_WRAP'
        <?php
        namespace App\Api;
        use Foo\Bar, \Baz\Qux AS Q ;
        use Group\Sub\{ One, Two as Deux, function helper, const LIMIT, Three, };
        use function strlen, trim;
        use const PHP_EOL;
        /* use Commented\Out; */
        // use Line\Out;
        use Plain;
        final class Input
        {
            use SomeTrait;
        
            public function run(string $value): string
            {
                $closure = function () use ($value): string {
                    return "{$value}";
                };
        
                return $closure() . namespace\Local::class;
            }
        }
        use After\Klass;
        PHP_WRAP;
        $this->assertSame([
            'bar' => 'Foo\Bar',
            'q' => 'Baz\Qux',
            'one' => 'Group\Sub\One',
            'deux' => 'Group\Sub\Two',
            'three' => 'Group\Sub\Three',
            'plain' => 'Plain',
            'klass' => 'After\Klass',
        ], TypeName::importsOf($code, 'App\Api'));
        $this->assertSame([], TypeName::importsOf($code, 'Other'));
        $this->assertSame([], TypeName::importsOf('', ''));
        $this->assertSame(['b' => 'A\B', 'c' => 'C'], TypeName::importsOf('<?php use A\B; use C;', ''));
    }

    public function testImportsOfBracketedNamespaces(): void
    {
        $code = <<<'PHP'
            <?php
            namespace First {
                use A\Only;
                function f(): void { if (true) { } }
            }
            namespace Second\Level {
                use B\Here;
                use C\{ D };
                class K { use T; }
            }
            namespace {
                use Global\Thing;
            }
            PHP;
        $this->assertSame(['only' => 'A\Only'], TypeName::importsOf($code, 'First'));
        $this->assertSame(['here' => 'B\Here', 'd' => 'C\D'], TypeName::importsOf($code, 'Second\Level'));
        $this->assertSame(['thing' => 'Global\Thing'], TypeName::importsOf($code, ''));
    }

    public function testDollarBraceInterpolationKeepsTheDepth(): void
    {
        $code = '<?php namespace N; class K { function f($v) { return "${v}"; } } use Late\Import;';
        $this->assertSame(['import' => 'Late\Import'], TypeName::importsOf($code, 'N'));
    }
}

