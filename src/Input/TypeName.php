<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Resolves a class name written in a list&lt;T&gt; PHPDoc like PHP resolves it in the DTO's file:
 * a leading backslash is fully qualified, an imported alias (also as the first part of a qualified name)
 * is replaced by its import, anything else is relative to the namespace of the class.
 */
final class TypeName
{
    private const array BUILTIN = ['string', 'int', 'float', 'bool', 'array', 'mixed', 'object', 'null'];

    /** @param \ReflectionClass<*> $scope */
    public static function resolve(string $name, \ReflectionClass $scope): string
    {
        $name = trim($name);
        $bare = ltrim($name, '\\');
        if (in_array(strtolower($bare), self::BUILTIN, true)) {
            return strtolower($bare);
        }
        if (str_starts_with($name, '\\')) {
            return $bare;
        }

        $imports = self::imports($scope);
        $first = explode('\\', $name, 2);
        $imported = $imports[strtolower($first[0])] ?? null;
        if ($imported !== null) {
            return isset($first[1]) ? $imported . '\\' . $first[1] : $imported;
        }

        $namespace = $scope->getNamespaceName();
        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /**
     * Class imports of the namespace block of the class, keyed by the lowercase alias.
     *
     * @param \ReflectionClass<*> $scope
     * @return array<string, string>
     */
    private static function imports(\ReflectionClass $scope): array
    {
        $file = $scope->getFileName();
        $code = is_string($file) && is_file($file) ? file_get_contents($file) : '';

        return self::importsOf(is_string($code) ? $code : '', $scope->getNamespaceName());
    }

    /**
     * Top-level class imports of one namespace block in PHP code, keyed by the lowercase alias.
     *
     * @internal
     * @return array<string, string>
     */
    public static function importsOf(string $code, string $namespace): array
    {
        $tokens = array_values(array_filter(\PhpToken::tokenize($code), static fn (\PhpToken $token): bool => !$token->isIgnorable()));
        $imports = [];
        $current = '';
        // braces opened inside the current namespace; a `use` at 0 is an import
        $level = 0;
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            if ($token->text === '}') {
                $level--;
            } elseif ($token->text === '{' || $token->is(T_DOLLAR_OPEN_CURLY_BRACES)) {
                $level++;
            } elseif ($token->is(T_NAMESPACE)) {
                $current = '';
                for ($i++; $tokens[$i]->text !== ';' && $tokens[$i]->text !== '{'; $i++) {
                    $current = $tokens[$i]->text;
                }
                $level = 0;
            } elseif ($token->is(T_USE) && $level === 0) {
                $i = self::useStatement($tokens, $i, $current === $namespace, $imports);
            }
        }

        return $imports;
    }

    /**
     * Reads one `use` statement from its `use` token and returns the index of its `;`.
     *
     * @param list<\PhpToken> $tokens
     * @param array<string, string> $imports
     */
    private static function useStatement(array $tokens, int $i, bool $keep, array &$imports): int
    {
        $prefix = '';
        $name = '';
        $alias = '';
        $skip = false;
        for (; $tokens[$i]->text !== ';'; $i++) {
            $token = $tokens[$i];
            if ($token->text === '{') {
                $prefix = $name . '\\';
                $name = '';
            } elseif ($token->is([T_FUNCTION, T_CONST])) {
                $skip = true;
            } elseif ($token->text === ',' || $token->text === '}') {
                self::remember($imports, $keep && !$skip, $prefix, $name, $alias);
                [$name, $alias, $skip] = ['', '', $skip && $prefix === ''];
            } elseif ($token->is(T_AS)) {
                $alias = $tokens[++$i]->text;
            } elseif (!$token->is(T_NS_SEPARATOR)) {
                $name = ltrim($token->text, '\\');
            }
        }
        self::remember($imports, $keep && !$skip, $prefix, $name, $alias);

        return $i;
    }

    /**
     * @param array<string, string> $imports
     */
    private static function remember(array &$imports, bool $keep, string $prefix, string $name, string $alias): void
    {
        if (!$keep || $name === '') {
            return;
        }
        $segments = explode('\\', $name);
        $imports[strtolower($alias !== '' ? $alias : end($segments))] = $prefix . $name;
    }
}
