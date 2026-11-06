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
use Tomaj\NetteApi\Validation\Email;

final class TypeNameTest extends TestCase
{
    public function testResolvesImportsAliasesAndBuiltins(): void
    {
        $scope = new \ReflectionClass(ImportScope::class);
        $this->assertSame('string', TypeName::resolve(' string ', $scope));
        $this->assertSame('string', TypeName::resolve('\\string', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\RangeAlias', TypeName::resolve(' RangeAlias ', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\RangeAlias', TypeName::resolve('\\RangeAlias', $scope));
        $this->assertSame(Email::class, TypeName::resolve('EmailAlias', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\Email', TypeName::resolve('Email', $scope));
        $this->assertSame(Query::class, TypeName::resolve('Q', $scope));
        $this->assertSame(Body::class, TypeName::resolve('Body', $scope));
        $this->assertSame(File::class, TypeName::resolve('File', $scope));
        $this->assertSame('Myfunction\\Widget', TypeName::resolve('Widget', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\RangeAlias', TypeName::resolve('RangeAlias', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\Ghost', TypeName::resolve('Ghost', $scope));
        $this->assertSame('Tomaj\\NetteApi\\Test\\Input\\Fixtures\\strlen', TypeName::resolve('strlen', $scope));
        $this->assertSame(Email::class, TypeName::resolve(Email::class, $scope));
        eval('namespace FooEval { class Bar {} }');
        $this->assertSame('FooEval\\Widget', TypeName::resolve('Widget', new \ReflectionClass(Bar::class)));
    }
}
