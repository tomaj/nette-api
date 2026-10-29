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
        FileSystem::createDir(__DIR__ . '/../log');
        $configurator->enableTracy(__DIR__ . '/../log');
        $configurator->setTempDirectory(__DIR__ . '/../temp');
        $configurator->addConfig(__DIR__ . '/../config/common.neon');
        return $configurator;
    }
}
