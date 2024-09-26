<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Error;

use Nette\Http\Response;
use Throwable;
use Tomaj\NetteApi\Output\Configurator\ConfiguratorInterface;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tracy\Debugger;

final class DefaultErrorHandler implements ErrorHandlerInterface
{
    /** @var ConfiguratorInterface */
    public $outputConfigurator;

    public function __construct(ConfiguratorInterface $outputConfigurator)
    {
        $this->outputConfigurator = $outputConfigurator;
    }

    public function handle(Throwable $exception): JsonApiResponse
    {
        Debugger::log($exception, Debugger::EXCEPTION);
        if ($this->outputConfigurator->showErrorDetail()) {
            $response = new JsonApiResponse(Response::S500_INTERNAL_SERVER_ERROR, ['status' => 'error', 'message' => 'Internal server error', 'detail' => $exception->getMessage()]);
        } else {
            $response = new JsonApiResponse(Response::S500_INTERNAL_SERVER_ERROR, ['status' => 'error', 'message' => 'Internal server error']);
        }
        return $response;
    }
}
