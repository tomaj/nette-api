<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output\Configurator;

use Nette\Http\Request;

class QueryConfigurator implements ConfiguratorInterface
{
    /**
     * @param string $schemaValidateParam Name of get parameter to enable schema validation
     * @param string $errorDetailParam Name of get parameter to show additional info in error response
     */
    public function __construct(
        private Request $request,
        private string $schemaValidateParam = 'schema_validate',
        private string $errorDetailParam = 'error_detail'
    ) {
    }

    public function validateSchema(): bool
    {
        $getParam = $this->request->getQuery($this->schemaValidateParam);
        return $getParam !== null && $getParam !== '0' && $getParam !== 'false';
    }

    public function showErrorDetail(): bool
    {
        $getParam = $this->request->getQuery($this->errorDetailParam);
        return $getParam !== null && $getParam !== '0' && $getParam !== 'false';
    }
}
