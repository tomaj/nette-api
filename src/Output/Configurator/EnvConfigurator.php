<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

class EnvConfigurator implements ConfiguratorInterface
{
    /**
     * @param string $envVariable Which environment variable to check for production value
     * @param string $productionValue Value that indicates production environment eg. 'production' or 'prod'...
     */
    public function __construct(
        private string $envVariable = 'APP_ENV',
        private string $productionValue = 'production'
    ) {
    }

    public function validateSchema(): bool
    {
        $appEnv = getenv($this->envVariable);
        if ($appEnv === $this->productionValue) {
            return false;
        }

        return true;
    }

    public function showErrorDetail(): bool
    {
        $appEnv = getenv($this->envVariable);
        if ($appEnv === $this->productionValue) {
            return false;
        }

        return true;
    }
}
