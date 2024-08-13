<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

use Nette\Application\Request;

class QueryConfigurator implements ConfiguratorInterface
{
    private $noSchemaValidateParam = 'no_schema_validate';
    private $errorDetailParam = 'error_detail';

    /**
     * @param string $noSchemaValidateParam Name of get parameter to disable schema validation
     * @param string $errorDetailParam Name of get parameter to show additional info in error response
     */
    public function __construct(string $noSchemaValidateParam = 'no_schema_validate', string $errorDetailParam = 'error_detail')
    {
        $this->noSchemaValidateParam = $noSchemaValidateParam;
        $this->errorDetailParam = $errorDetailParam;
    }

    public function validateSchema(?Request $request = null): bool
    {
        if ($request === null) {
            return false;
        }
        $getParams = $request->getParameters();
        return !isset($getParams[$this->noSchemaValidateParam]);
    }

    public function showErrorDetail(?Request $request = null): bool
    {
        if ($request === null) {
            return false;
        }
        $getParams = $request->getParameters();
        return isset($getParams[$this->errorDetailParam]);
    }
}
