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
     * Classes whose constants or enum cases the file of the class reads, as in `#[Length(max: Limits::NAME)]` or `= Status::Active`.
     * Names are resolved in the namespace of the class, and a class read twice is listed twice.
     *
     * @param \ReflectionClass<*> $scope
     * @return list<string>
     */
    public static function constantClasses(\ReflectionClass $scope): array
    {
        $tokens = self::tokens(self::code($scope));
        $classes = [];
        foreach ($tokens as $i => $token) {
            // valid code never ends right after a name, the fallbacks only keep the lookahead inside the list
            [$separator, $member, $after] = [$tokens[$i + 1] ?? $token, $tokens[$i + 2] ?? $token, $tokens[$i + 3] ?? $token];
            if ($token->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED]) && $separator->is(T_DOUBLE_COLON) && $member->is(T_STRING) && $after->text !== '(') {
                // self::X and parent::X resolve to no class, so the caller drops them
                $classes[] = self::resolve($token->text, $scope);
            }
        }

        return $classes;
    }

    /**
     * Class imports of the namespace block of the class, keyed by the lowercase alias.
     *
     * @param \ReflectionClass<*> $scope
     * @return array<string, string>
     */
    private static function imports(\ReflectionClass $scope): array
    {
        return self::importsOf(self::code($scope), $scope->getNamespaceName());
    }

    /** @param \ReflectionClass<*> $scope */
    private static function code(\ReflectionClass $scope): string
    {
        $file = $scope->getFileName();
        $code = is_string($file) && is_file($file) ? file_get_contents($file) : '';

        return is_string($code) ? $code : '';
    }

    /**
     * @return list<\PhpToken>
     */
    private static function tokens(string $code): array
    {
        return array_values(array_filter(\PhpToken::tokenize($code), static fn (\PhpToken $token): bool => !$token->isIgnorable()));
    }

    /**
     * Top-level class imports of one namespace block in PHP code, keyed by the lowercase alias.
     *
     * @internal
     * @return array<string, string>
     */
    public static function importsOf(string $code, string $namespace): array
    {
        $tokens = self::tokens($code);
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
            } elseif ($token->is(T_USE) && $level === 0 && $tokens[$i + 1]->text !== '(') {
                // `function () use ($x)` of a closure outside any class is not an import
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
