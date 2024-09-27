<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Error;

use Nette\Http\Response;
use Throwable;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
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

    public function handleInputParams(array $errors): JsonApiResponse
    {
        if ($this->outputConfigurator->showErrorDetail()) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'wrong input', 'detail' => $errors]);
        } else {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, ['status' => 'error', 'message' => 'wrong input']);
        }
        return $response;
    }

    public function handleSchema(array $errors): JsonApiResponse
    {
        Debugger::log($errors, Debugger::ERROR);

        if ($this->outputConfigurator->showErrorDetail()) {
            $response = new JsonApiResponse(Response::S500_INTERNAL_SERVER_ERROR, ['status' => 'error', 'message' => 'Internal server error', 'detail' => $errors]);
        } else {
            $response = new JsonApiResponse(Response::S500_INTERNAL_SERVER_ERROR, ['status' => 'error', 'message' => 'Internal server error']);
        }
        return $response;
    }

    public function handleAuthorization(ApiAuthorizationInterface $auth): JsonApiResponse
    {
        return new JsonApiResponse(Response::S403_FORBIDDEN, ['status' => 'error', 'message' => $auth->getErrorMessage()]);
    }

    public function handleAuthorizationException(Throwable $exception): JsonApiResponse
    {
        return new JsonApiResponse(Response::S403_FORBIDDEN, ['status' => 'error', 'message' => $exception->getMessage()]);
    }
}
