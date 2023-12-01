<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Link;

use Latte\Extension;

final class ApiLinkExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'apiLink' => [ApiLinkNode::class, 'create'],
        ];
    }
}
