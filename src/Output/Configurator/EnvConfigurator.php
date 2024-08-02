<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

use Nette\Application\Request;

class EnvConfigurator implements ConfiguratorInterface
{
    private string $envVariable = "APP_ENV";
    private string $productionValue = "production";

    /**
     * @param string $envVariable Which environment variable to check for production value
     * @param string $productionValue Value that indicates production environment eg. "production" or "prod"...
     */
    public function __construct(string $envVariable = "APP_ENV", string $productionValue = "production")
    {
        $this->envVariable = $envVariable;
        $this->productionValue = $productionValue;
    }

    public function validateSchema(?Request $request = null): bool
    {
        $appEnv = getenv($this->envVariable);
        if ($appEnv === $this->productionValue) {
            return false;
        }
        return true;
    }

    public function showErrorDetail(?Request $request = null): bool
    {
        $appEnv = getenv($this->envVariable);
        if ($appEnv === $this->productionValue) {
            return false;
        }
        return true;
    }
}
