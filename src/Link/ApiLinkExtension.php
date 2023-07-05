<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Link;

use Latte\Compiler\Tag;
use Latte\Compiler\TemplateParser;
use Latte\Extension;
use Tomaj\NetteApi\EndpointIdentifier;

final class ApiLinkExtension extends Extension
{
    public function getTags(): array
    {
        return [
            'apiLink' => [ApiLinkNode::class, 'create'],
        ];
    }
}
