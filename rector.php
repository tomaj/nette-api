<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
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
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(typeDeclarations: true, phpunitCodeQuality: true)
    ->withComposerBased(phpunit: true)
    ->withAttributesSets(phpunit: true)
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withRules([
        SafeDeclareStrictTypesRector::class,
    ]);
