<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

class EnvConfigurator implements ConfiguratorInterface
{
    private $envVariable = 'APP_ENV';
    private $productionValue = 'production';

    /**
     * @param string $envVariable Which environment variable to check for production value
     * @param string $productionValue Value that indicates production environment eg. 'production' or 'prod'...
     */
    public function __construct(string $envVariable = 'APP_ENV', string $productionValue = 'production')
    {
        $this->envVariable = $envVariable;
        $this->productionValue = $productionValue;
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
