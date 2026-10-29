<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Error;

use Tomaj\NetteApi\Output\Configurator\ConfiguratorInterface;

final readonly class StaticConfigurator implements ConfiguratorInterface
{
    public function __construct(private bool $showErrorDetail)
    {
    }

    #[\Override]
    public function validateSchema(): bool
    {
        return true;
    }

    #[\Override]
    public function showErrorDetail(): bool
    {
        return $this->showErrorDetail;
    }
}
