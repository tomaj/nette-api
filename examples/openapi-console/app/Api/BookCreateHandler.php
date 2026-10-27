<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class BookCreateHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'Add a book';
    }

    public function tags(): array
    {
        return ['books'];
    }

    public function params(): array
    {
        $schema = '{"type": "object", "properties": {"title": {"type": "string"}, "year": {"type": "integer"}}, "required": ["title"]}';
        return [
            (new JsonInputParam('book', $schema))
                ->setRequired()
                ->addExample('Foundation', ['title' => 'Foundation', 'year' => 1951]),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        return new JsonApiResponse(201, ['book' => ['id' => 3] + $params['book']]);
    }

    public function outputs(): array
    {
        return [
            new JsonOutput(201, '{"type": "object", "properties": {"book": {"type": "object"}}, "required": ["book"]}', 'The new book'),
        ];
    }
}
