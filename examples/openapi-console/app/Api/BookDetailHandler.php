<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\PathInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\InputType;

/**
 * GET /api/v1/books/{id}: the id comes from the URL template of the endpoint.
 */
final class BookDetailHandler extends BaseHandler
{
    private const BOOKS = [
        1 => ['id' => 1, 'title' => 'Dune', 'year' => 1965],
        2 => ['id' => 2, 'title' => 'Neuromancer', 'year' => 1984],
    ];

    public function summary(): string
    {
        return 'Show a book';
    }

    public function tags(): array
    {
        return ['books'];
    }

    public function params(): array
    {
        return [
            (new PathInputParam('id', InputType::INTEGER))->setDescription('Id of the book'),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $book = self::BOOKS[$params['id']] ?? null;
        return $book === null
            ? new JsonApiResponse(404, ['status' => 'error', 'message' => 'Book not found'])
            : new JsonApiResponse(200, ['book' => $book]);
    }
}
