<?php

declare(strict_types=1);

namespace App\Api;

use App\MaintenanceException;
use RuntimeException;
use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\ResponseInterface;

/**
 * Always throws, to show what the error handler does with an exception.
 */
final class FailHandler extends BaseHandler
{
    public function params(): array
    {
        return [
            (new GetInputParam('kind'))->setAvailableValues(['crash', 'maintenance'])->setRequired(),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        if ($params['kind'] === 'maintenance') {
            throw new MaintenanceException('Database migration is running');
        }
        throw new RuntimeException('Database connection refused');
    }
}
