<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\InputType;

final class BookListHandler extends BaseHandler
{
    private const BOOKS = [
        ['id' => 1, 'title' => 'Dune', 'year' => 1965],
        ['id' => 2, 'title' => 'Neuromancer', 'year' => 1984],
    ];

    // summary, description and tags end up in the OpenAPI document and the console
    public function summary(): string
    {
        return 'List books';
    }

    public function description(): string
    {
        return 'Returns books published in the given year or later.';
    }

    public function tags(): array
    {
        return ['books'];
    }

    public function params(): array
    {
        return [
            (new GetInputParam('since', InputType::INTEGER))
                ->setDescription('Earliest publication year')
                ->addExample('eighties', 1980),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $books = array_values(array_filter(self::BOOKS, fn (array $book): bool => $book['year'] >= ($params['since'] ?? 0)));
        return new JsonApiResponse(200, ['books' => $books]);
    }

    public function outputs(): array
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'books' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'title' => ['type' => 'string'],
                            'year' => ['type' => 'integer'],
                        ],
                        'required' => ['id', 'title', 'year'],
                    ],
                ],
            ],
            'required' => ['books'],
        ];
        return [
            (new JsonOutput(200, (string) json_encode($schema), 'Books'))
                ->addExample('two books', ['books' => self::BOOKS]),
        ];
    }
}
