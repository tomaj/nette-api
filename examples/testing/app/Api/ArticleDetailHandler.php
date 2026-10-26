<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Output\JsonOutput;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class ArticleDetailHandler extends BaseHandler
{
    public const SCHEMA = '{"type":"object","required":["id","title"],"properties":{"id":{"type":"integer"},"title":{"type":"string"}},"additionalProperties":false}';

    public const ERROR_SCHEMA = '{"type":"object","required":["status","message"],"properties":{"status":{"const":"error"},"message":{"type":"string"}}}';

    public function __construct(private ArticleRepository $articles)
    {
        parent::__construct();
    }

    public function params(): array
    {
        // untyped: in a CLI test $_GET values skip the type cast that HTTP requests get
        return [(new GetInputParam('id'))->setRequired()];
    }

    public function outputs(): array
    {
        return [new JsonOutput(200, self::SCHEMA), new JsonOutput(404, self::ERROR_SCHEMA)];
    }

    public function handle(array $params): ResponseInterface
    {
        $id = (int) $params['id'];
        $title = $this->articles->find($id);
        if ($title === null) {
            return new JsonApiResponse(404, ['status' => 'error', 'message' => 'Article not found']);
        }

        return new JsonApiResponse(200, ['id' => $id, 'title' => $title]);
    }
}
