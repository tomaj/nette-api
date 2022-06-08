<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Link;

use Latte\Compiler;
use Latte\MacroNode;
use Latte\Macros\MacroSet;
use Latte\PhpWriter;
use Nette\Application\UI\InvalidLinkException;
use Tracy\Debugger;

/**
 * Usage in latte:
 * {apiLink $method, $version, $package, $apiAction, ['title' => 'My title', 'data-foo' => 'bar']}
 */
class ApiLinkMacro extends MacroSet
{
    public static function install(Compiler $compiler)
    {
        $macroSet = new static($compiler);
        $macroSet->addMacro('apiLink', [self::class, 'start']);
    }

    public static function start(MacroNode $node, PhpWriter $writer)
    {
        $args = array_map('trim', explode(',', $node->args, 5));

        if (count($args) < 3) {
            $message = "Invalid link destination, too few arguments.";
            if (!Debugger::$productionMode) {
                throw new InvalidLinkException($message);
            }
            Debugger::log($message, Debugger::EXCEPTION);
            return '';
        }

        $arguments = [
            'method' => self::addQuotes($args[0]),
            'version' => $args[1],
            'package' => self::addQuotes($args[2]),
            'action' => isset($args[3]) ? self::addQuotes($args[3]) : 'null',
            'params' => $args[4] ?? '[]',
        ];

        return $writer->write('echo ($this->filters->apiLink)((new Tomaj\NetteApi\EndpointIdentifier(' .
            $arguments['method']  . ', ' .
            $arguments['version']  . ', ' .
            $arguments['package'] . ', ' .
            $arguments['action'] . ')), ' . $arguments['params'] . ')');
    }

    private static function addQuotes($string)
    {
        return '"' . trim($string, "'\"") . '"';
    }
}
