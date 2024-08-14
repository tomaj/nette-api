<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

use Nette\Http\Request;

class QueryConfigurator implements ConfiguratorInterface
{
    private $schemaValidateParam = 'schema_validate';
    private $errorDetailParam = 'error_detail';
    public $request = null;

    /**
     * @param string $schemaValidateParam Name of get parameter to enable schema validation
     * @param string $errorDetailParam Name of get parameter to show additional info in error response
     */
    public function __construct(
        Request $request,
        string $schemaValidateParam = 'schema_validate', 
        string $errorDetailParam = 'error_detail'
    ) {
        $this->request = $request;
        $this->schemaValidateParam = $schemaValidateParam;
        $this->errorDetailParam = $errorDetailParam;
    }

    public function validateSchema(): bool
    {
        $getParams = $this->request->getQuery();
        return isset($getParams[$this->schemaValidateParam]);
    }

    public function showErrorDetail(): bool
    {
        $getParams = $this->request->getQuery();
        return isset($getParams[$this->errorDetailParam]);
    }
}
