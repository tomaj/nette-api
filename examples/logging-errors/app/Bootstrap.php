<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;

final class Bootstrap
{
    public static function boot(): Configurator
    {
        $configurator = new Configurator();
        // Tracy writes the exceptions logged by DefaultErrorHandler to log/
        $configurator->enableTracy(__DIR__ . '/../log');
        $configurator->setTempDirectory(__DIR__ . '/../temp');
        $configurator->addConfig(__DIR__ . '/../config/common.neon');
        return $configurator;
    }
}
