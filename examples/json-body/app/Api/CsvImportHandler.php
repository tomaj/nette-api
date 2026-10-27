<?php

declare(strict_types=1);

namespace App\Api;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Params\RawInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

final class CsvImportHandler extends BaseHandler
{
    public function summary(): string
    {
        return 'Import products from a CSV body';
    }

    public function params(): array
    {
        return [
            // the body as a string, nette-api only checks that it is not empty
            (new RawInputParam('csv'))->setRequired()->setExample("sku,price\nbook,12.5\npen,1.2"),
        ];
    }

    public function handle(array $params): ResponseInterface
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $params['csv']))));
        $header = str_getcsv(array_shift($lines), escape: '');
        if ($header !== ['sku', 'price']) {
            // the format is checked here, in the handler, and answered with your own code
            return new JsonApiResponse(422, ['status' => 'error', 'message' => 'The first line must be sku,price']);
        }

        $products = [];
        foreach ($lines as $line) {
            [$sku, $price] = str_getcsv($line, escape: '') + [1 => '0'];
            $products[] = ['sku' => $sku, 'price' => (float) $price];
        }

        return new JsonApiResponse(200, ['imported' => count($products), 'products' => $products]);
    }
}
