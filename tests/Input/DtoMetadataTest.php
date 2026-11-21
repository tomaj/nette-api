<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\DtoMetadataRegistry;
use Tomaj\NetteApi\Input\DtoCompiler;
use Tomaj\NetteApi\Input\DtoMetadata;
use Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\Dependency\DependencyHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\QueryThenBodyHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\Separate\NoteHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\Separate\NoteInput;
use Tomaj\NetteApi\Test\Input\Fixtures\TwoExamplesHandler;

final class DtoMetadataTest extends TestCase
{
    protected function setUp(): void
    {
        DtoMetadataRegistry::reset();
    }

    public function testCompiledOrderMatchesTheSnapshot(): void
    {
        ['metadata' => $metadata, 'files' => $files] = DtoCompiler::compileWithFiles(CreateOrderHandler::class);
        $expected = json_decode((string) file_get_contents(__DIR__ . '/Fixtures/create-order-export.json'), true);
        $this->assertSame($expected, self::snapshot($metadata));
        $this->assertTrue(array_is_list($files));
        $this->assertContains(realpath(__DIR__ . '/Fixtures/Handlers.php') ?: '', array_map(realpath(...), $files));
    }

    /**
     * Compiled metadata as plain data: objects become their class and properties, enum cases their class and value.
     */
    private static function snapshot(mixed $value): mixed
    {
        if ($value instanceof \UnitEnum) {
            return ['enum' => $value::class, 'value' => $value instanceof \BackedEnum ? $value->value : $value->name];
        }
        if (is_object($value)) {
            return ['@class' => $value::class] + array_map(self::snapshot(...), get_object_vars($value));
        }

        return is_array($value) ? array_map(self::snapshot(...), $value) : $value;
    }

    public function testSerializedMetadataRoundTrips(): void
    {
        $compiled = DtoCompiler::compile(CreateOrderHandler::class);
        $payload = serialize($compiled);
        $this->assertStringNotContainsString('Handlers.php', $payload, 'compile-time file paths stay out of the container');
        $this->assertEquals($compiled, unserialize($payload));

        $restored = unserialize(serialize(DtoCompiler::compile(TwoExamplesHandler::class)));
        $this->assertInstanceOf(DtoMetadata::class, $restored);
        $this->assertSame(['one' => 1, 'two' => 2], $restored->fields[0]->examples);
        $priority = unserialize(serialize(DtoCompiler::compile(ListUsersHandler::class)));
        $this->assertInstanceOf(DtoMetadata::class, $priority);
        $this->assertSame([1, 2], $priority->fields[3]->enumValues);
    }

    public function testQueryOnlyHandlerDoesNotReadABody(): void
    {
        $metadata = DtoCompiler::compile(ListUsersHandler::class);
        $this->assertFalse($metadata->readsBody);
        $this->assertFalse($metadata->bodyRequired);
        $this->assertNull($metadata->firstBody);
        $this->assertFalse($metadata->fields[0]->enumInt);
        $this->assertTrue($metadata->fields[3]->enumInt);
        $this->assertSame([1, 2], $metadata->fields[3]->enumValues);
    }

    public function testQueryBeforeBodyStillReadsTheBody(): void
    {
        $metadata = DtoCompiler::compile(QueryThenBodyHandler::class);
        $this->assertTrue($metadata->readsBody);
        $this->assertTrue($metadata->bodyRequired);
        $this->assertSame('email', $metadata->firstBody);
    }

    public function testHandlerAndDtoFilesAreBothRecorded(): void
    {
        $files = array_map(realpath(...), DtoCompiler::compileWithFiles(NoteHandler::class)['files']);
        $this->assertContains(realpath(new \ReflectionClass(NoteHandler::class)->getFileName() ?: ''), $files);
        $this->assertContains(realpath(new \ReflectionClass(NoteInput::class)->getFileName() ?: ''), $files);
    }

    public function testEveryReflectedDependencyRebuildsTheContainer(): void
    {
        $files = array_map(basename(...), DtoCompiler::compileWithFiles(DependencyHandler::class)['files']);
        foreach (['DependencyHandler.php', 'DependencyInput.php', 'DependencyParentInput.php', 'DependencyCaster.php', 'DependencyEnum.php', 'DependencyItemEnum.php', 'DependencyConstraint.php', 'DependencyTrait.php', 'DependencyNestedTrait.php', 'DependencyParentTrait.php', 'DependencyLimits.php', 'DependencyDefaults.php', 'DependencyLabels.php'] as $file) {
            $this->assertContains($file, $files);
        }
        // a constant read in an attribute or a default is copied into the metadata; a static call is not
        $this->assertNotContains('DependencyUnused.php', $files);
        $this->assertSame(array_values(array_unique($files)), $files);
        foreach (DtoCompiler::compileWithFiles(DependencyHandler::class)['files'] as $file) {
            $this->assertFileExists($file);
        }
    }

    public function testSameInstanceIsReusedUntilReset(): void
    {
        $first = DtoMetadataRegistry::forHandler(ListUsersHandler::class);
        $again = DtoMetadataRegistry::forHandler(ListUsersHandler::class);
        $this->assertSame($first, $again);

        $installed = new DtoMetadata($first->handler, $first->class, $first->fields, $first->contentTypes, $first->bodyRequired, $first->readsBody, 'from-install');
        DtoMetadataRegistry::reset();
        DtoMetadataRegistry::install([ListUsersHandler::class => serialize($installed)]);
        $this->assertSame('from-install', DtoMetadataRegistry::forHandler(ListUsersHandler::class)->firstBody);
        $this->assertSame(CreateOrderHandler::class, DtoMetadataRegistry::forHandler(CreateOrderHandler::class)->handler);
    }
}
