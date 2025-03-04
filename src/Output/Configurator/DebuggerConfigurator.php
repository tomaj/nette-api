<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

use Tracy\Debugger;

class DebuggerConfigurator implements ConfiguratorInterface
{
    public function validateSchema(): bool
    {
        return !Debugger::$productionMode;
    }

    public function showErrorDetail(): bool
    {
        return !Debugger::$productionMode;
    }
}
