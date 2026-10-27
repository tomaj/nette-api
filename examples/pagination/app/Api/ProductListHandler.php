<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Validation\InputType;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class ProductListHandler extends BaseHandler
{
    private const TOTAL = 23;

    private const ERROR_SCHEMA = '{"type":"object","required":["status","message"],"properties":{"status":{"const":"error"},"message":{"type":"string"}}}';

    public function summary(): string
    {
        return 'List products page by page';
    }

    public function params(): array
    {
        return [
            // InputType::INTEGER turns "2" into 2 before the handler sees it
            (new GetInputParam('page', InputType::INTEGER))->setDefault(1)->setDescription('From 1'),
            // only these values pass, anything else answers 400
            (new GetInputParam('per_page', InputType::INTEGER))->setDefault(10)->setAvailableValues([5, 10, 20]),
        ];
    }

    public function outputs(): array
    {
        // checked after handle() unless APP_ENV is production, see EnvConfigurator in the config
        return [
            new JsonOutput(200, (string) json_encode([
                'type' => 'object',
                'required' => ['items', 'page', 'per_page', 'total', 'links'],
                'properties' => [
                    'items' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['id', 'name']]],
                    'page' => ['type' => 'integer', 'minimum' => 1],
                    'per_page' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'links' => ['type' => 'object'],
                ],
            ])),
            // every code the handler returns needs an output, else the validation answers 500
            new JsonOutput(400, self::ERROR_SCHEMA),
            new JsonOutput(404, self::ERROR_SCHEMA),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        // a value that is not a number arrives as null, a range check is up to the handler
        $page = $params['page'];
        $perPage = $params['per_page'];
        if (!is_int($page) || $page < 1) {
            return new JsonApiResponse(400, ['status' => 'error', 'message' => 'page must be a whole number from 1']);
        }
        $pages = max(1, (int) ceil(self::TOTAL / $perPage));
        if ($page > $pages) {
            return new JsonApiResponse(404, ['status' => 'error', 'message' => "Page $page does not exist, last page is $pages"]);
        }

        $items = [];
        for ($id = ($page - 1) * $perPage + 1; $id <= min(self::TOTAL, $page * $perPage); $id++) {
            $items[] = ['id' => $id, 'name' => "Product $id"];
        }

        $links = ['self' => $this->link($page, $perPage)];
        if ($page > 1) {
            $links['prev'] = $this->link($page - 1, $perPage);
        }
        if ($page < $pages) {
            $links['next'] = $this->link($page + 1, $perPage);
        }

        return new JsonApiResponse(200, [
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'total' => self::TOTAL,
            'links' => $links,
        ]);
    }

    private function link(int $page, int $perPage): string
    {
        return '/api/v1/products?' . http_build_query(['page' => $page, 'per_page' => $perPage]);
    }
}
