<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

use Nette\Application\Request;
use Tracy\Debugger;

class DebuggerConfigurator implements ConfiguratorInterface
{
    public function validateSchema(?Request $request = null): bool
    {
        return !Debugger::$productionMode;
    }

    public function showErrorDetail(?Request $request = null): bool
    {
        return !Debugger::$productionMode;
    }
}
