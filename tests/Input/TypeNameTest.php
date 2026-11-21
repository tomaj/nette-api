<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use Tomaj\NetteApi\Test\Input\Fixtures\Dependency\DependencyLimits;
use Tomaj\NetteApi\Test\Input\Fixtures\Dependency\DependencyDefaults;
use FooEval\Bar;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Test\Input\Fixtures\Dependency\DependencyParentInput;
use Tomaj\NetteApi\Test\Input\Fixtures\Dependency\DependencyTrait;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\File;
use Tomaj\NetteApi\Input\Query;
use Tomaj\NetteApi\Input\DtoMetadataRegistry;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Input\TypeName;
use Tomaj\NetteApi\Test\Input\Fixtures\ImportScope;
use Tomaj\NetteApi\Test\Input\Fixtures\SecondScope;
use Tomaj\NetteApi\Validation\Email;
use Tomaj\NetteApi\Validation\Range;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Test\Input\Fixtures\Inherited\LeafLevelsHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\Priority;

final class TypeNameTest extends TestCase
{
    protected function setUp(): void
    {
        RequestInput::reset();
        DtoMetadataRegistry::reset();
        $_GET = [];
    }

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

    public function testAClosureOutsideAClassIsNotAnImport(): void
    {
        $code = '<?php namespace N; use A\\First; $f = function () use ($x) { return 1; }; use B\\Second;';
        $this->assertSame(['first' => 'A\\First', 'second' => 'B\\Second'], TypeName::importsOf($code, 'N'));
    }

    public function testConstantClassesListsTheClassesWhoseConstantsAreRead(): void
    {
        $this->assertSame([
            DependencyLimits::class,
            DependencyDefaults::class,
        ], TypeName::constantClasses(new \ReflectionClass(DependencyParentInput::class)));
        $this->assertSame(['Tomaj\\NetteApi\\Test\\Input\\Fixtures\\Dependency\\self'], TypeName::constantClasses(new \ReflectionClass(DependencyTrait::class)), 'a static call is left out, self is dropped by the caller');
    }

    public function testDollarBraceInterpolationKeepsTheDepth(): void
    {
        $code = '<?php namespace N; class K { function f($v) { return "${v}"; } } use Late\Import;';
        $this->assertSame(['import' => 'Late\Import'], TypeName::importsOf($code, 'N'));
    }

    public function testInheritedListDocblockResolvesInTheDeclaringClass(): void
    {
        $fields = DtoCompiler::compile(LeafLevelsHandler::class)->fields;
        $this->assertSame(Priority::class, $fields[0]->listItem);
        $this->assertSame(Priority::class, $fields[1]->listItem, 'the @return of an inherited cast() resolves in the parent caster');

        $_GET = ['levels' => ['2', '1'], 'casted' => '1,2'];
        $handler = new LeafLevelsHandler();
        $processor = new ParamsProcessor($handler->params());
        $this->assertFalse($processor->isError());
        $payload = $handler->handle($processor->getValues())->getPayload();
        $this->assertIsArray($payload);
        $this->assertSame([Priority::High, Priority::Low], $payload['levels']);
        $this->assertSame([Priority::Low, Priority::High], $payload['casted']);
    }
}
