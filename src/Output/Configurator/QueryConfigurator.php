<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

use Nette\Application\Request;

class QueryConfigurator implements ConfiguratorInterface
{
    /*
    ### Disable schema validation in not production environment
    Include get parameter no_schema_validate in your request to disable schema validation. This is useful for testing purposes.
    * schema validation is disabled by default in production environment for performance reasons

    ### Add additional info to error response 
    Include get parameter error_detail in your request to show additional info in error response. This is useful for debugging purposes.
    */
    public function validateSchema(?Request $request = null): bool
    {
        if ($request === null) {
            return false;
        }
        $getParams = $request->getParameters();
        return !isset($getParams['no_schema_validate']);
    }

    public function showErrorDetail(?Request $request = null): bool
    {
        if ($request === null) {
            return false;
        }
        $getParams = $request->getParameters();
        return isset($getParams['error_detail']);
    }
}