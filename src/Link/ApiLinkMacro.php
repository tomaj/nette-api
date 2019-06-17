<?php

namespace Tomaj\NetteApi\Link;

use Efabrica\Cms\Core\Macro\PageLink;
use Latte\Compiler;
use Latte\MacroNode;
use Latte\Macros\MacroSet;
use Latte\PhpWriter;
use Tomaj\NetteApi\EndpointIdentifier;

///**
// * Usage in latte:
// * {php $params = ['title' => 'My title', 'data-foo' => 'bar']}
// * {apiLink $version, $package, $apiAction, $params}
// */
class ApiLinkMacro extends MacroSet
{
    public static function install(Compiler $compiler)
    {
        $macroSet = new static($compiler);
        $macroSet->addMacro('apiLink',[ApiLinkMacro::class, 'start']);
    }

    public static function start(MacroNode $node, PhpWriter $writer)
    {
        $args = array_map('trim', explode(',', $node->args, 3));
        $arguments = [
            'version' => $args[0] ?? 'null',
            'package' => $args[1] ?? 'null',
            'action' => $args[2] ?? 'null',
            'params' => $args[3] ?? '[]',
        ];

        return $writer->write('echo \Tomaj\NetteApi\Link\ApiLinkMacro::createLink($_presenter->context->getByType("' .
            ApiLink::class . '"), ' .
            $arguments['version']  . ', ' .
            $arguments['package'] . ', ' .
            $arguments['action'] . ', ' .
            $arguments['params'] . ')');
    }

    /**
     * @internal
     */
    public static function createLink(ApiLink $apiLink, $version, $package, $apiAction = '', $params = [])
    {
        $endpoint = new EndpointIdentifier('GET', $version, $package, $apiAction);
        return $apiLink->link($endpoint, $params);
    }
}
