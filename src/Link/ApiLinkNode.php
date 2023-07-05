<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Link;

use Latte\Compiler\Nodes\Php\Expression\ArrayNode;
use Latte\Compiler\Nodes\StatementNode;
use Latte\Compiler\PrintContext;
use Latte\Compiler\Tag;

final class ApiLinkNode extends StatementNode
{
    /** @var ArrayNode $endpointArgs */
    public $endpointArgs;

    /** @var ArrayNode $endpointParams */
    public $endpointParams;

    public static function create(Tag $tag): ?ApiLinkNode
    {
        $tag->expectArguments();
        $node = new self();

        $tag->parser->stream->tryConsume(',');
        $args = $tag->parser->parseArguments();

        $allParameters = $args->items;
        $node->endpointArgs = new ArrayNode(array_slice($allParameters, 0, 4));
        $node->endpointParams = new ArrayNode(array_slice($allParameters, 4));

        return $node;
    }

    public function print(PrintContext $context): string
    {
        return $context->format('echo ($this->filters->apiLink)(new Tomaj\NetteApi\EndpointIdentifier(%args), %args);', $this->endpointArgs, $this->endpointParams);
    }


    public function &getIterator(): \Generator
    {
        yield $this->endpointArgs;
        yield $this->endpointParams;
    }
}
