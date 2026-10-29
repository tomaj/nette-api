<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use Nette\Http\IResponse;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

/**
 * Handler ApiDecider returns when no api matches the request
 *
 * ApiPresenter does not call handle(), it asks ErrorHandlerInterface::handleNotFound() for the 404,
 * so the response follows the configured error format. Customize the 404 there, not by extending this class.
 */
final class DefaultHandler extends BaseHandler
{
    /**
     * 3.x 404 body, the same as DefaultErrorHandler::handleNotFound(), for code that calls the handler directly
     *
     * @param array<string,mixed> $params
     */
    #[\Override]
    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(IResponse::S404_NOT_FOUND, ['status' => 'error', 'message' => 'Unknown api endpoint']);
    }
}
