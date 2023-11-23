<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Transformer;

use League\Fractal\TransformerAbstract;

class DummyTransformer extends TransformerAbstract
{
    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'string',
                ],
            ],
        ];
    }
}
