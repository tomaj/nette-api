<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;
use Nette\Utils\FileSystem;

final class Bootstrap
{
    public static function boot(): Configurator
    {
        $configurator = new Configurator();
        // Tracy writes the exceptions logged by ProblemDetailsErrorHandler to log/, which has to exist
        FileSystem::createDir(__DIR__ . '/../log');
        $configurator->enableTracy(__DIR__ . '/../log');
        $configurator->setTempDirectory(__DIR__ . '/../temp');
        $configurator->addConfig(__DIR__ . '/../config/common.neon');
        // ERRORS=negotiate: problem details only for clients that send Accept: application/problem+json
        if (getenv('ERRORS') === 'negotiate') {
            $configurator->addConfig(__DIR__ . '/../config/negotiate.neon');
        }
        return $configurator;
    }
}
