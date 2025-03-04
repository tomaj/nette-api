<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

interface ConfiguratorInterface
{
    public function validateSchema(): bool;

    public function showErrorDetail(): bool;
}
