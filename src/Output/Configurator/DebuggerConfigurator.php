<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

use Tracy\Debugger;

final class DebuggerConfigurator implements ConfiguratorInterface
{
    #[\Override]
    public function validateSchema(): bool
    {
        return !Debugger::$productionMode;
    }

    #[\Override]
    public function showErrorDetail(): bool
    {
        return !Debugger::$productionMode;
    }
}
