<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\ClassMethod\NarrowObjectReturnTypeRector;
use Rector\Renaming\Rector\MethodCall\RenameMethodRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        // composer-based PHPUnit set renames expectExceptionMessage() to expectExceptionMessageIsOrContains() (PHPUnit 13.2+), tests must also run on PHPUnit 12
        RenameMethodRector::class,
        // \Redis as the return type is a fatal error while loading the class without ext-redis, before the test can be skipped
        NarrowObjectReturnTypeRector::class => [__DIR__ . '/tests/RateLimit/Storage/PhpRedisStorageTest.php'],
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(typeDeclarations: true, phpunitCodeQuality: true)
    ->withComposerBased(phpunit: true)
    ->withAttributesSets(phpunit: true)
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withRules([
        SafeDeclareStrictTypesRector::class,
    ]);
