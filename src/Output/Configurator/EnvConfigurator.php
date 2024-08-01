<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

use Nette\Application\Request;

class EnvConfigurator implements ConfiguratorInterface
{
    public function validateSchema(?Request $request = null): bool
    {
        $appEnv = getenv("APP_ENV");
        if ($appEnv === "production") {
            return false;
        }
        return true;
    }

    public function showErrorDetail(?Request $request = null): bool
    {
        $appEnv = getenv("APP_ENV");
        if ($appEnv === "production") {
            return false;
        }
        return true;
    }
}