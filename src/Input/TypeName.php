<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Resolves a short class name written in a list&lt;T&gt; PHPDoc against the DTO's namespace and use statements.
 */
final class TypeName
{
    /** @param \ReflectionClass<*> $scope */
    public static function resolve(string $name, \ReflectionClass $scope): string
    {
        $name = ltrim(trim($name), '\\');
        if (in_array($name, ['string', 'int', 'float', 'bool', 'array', 'mixed', 'object', 'null'], true)) {
            return $name;
        }
        if (str_contains($name, '\\')) {
            return $name;
        }

        $imported = self::imports($scope)[$name] ?? null;
        if ($imported !== null) {
            return $imported;
        }

        $namespace = $scope->getNamespaceName();
        return $namespace === '' ? $name : $namespace . '\\' . $name;
    }

    /**
     * @param \ReflectionClass<*> $scope
     * @return array<string, string>
     */
    private static function imports(\ReflectionClass $scope): array
    {
        $file = $scope->getFileName();
        if ($file === false || !is_file($file)) {
            return [];
        }

        $code = file_get_contents($file);
        $imports = [];
        if (is_string($code)) {
            preg_match_all('/^use\s+([^;]+);/m', $code, $matches);
            foreach ($matches[1] as $clause) {
                if (preg_match('/(?:function|const)\s+/', $clause) === 1) {
                    continue;
                }
                if (preg_match('/(.+?)\s*\\\\\{\s*(.+)\s*\}/', $clause, $group) === 1) {
                    foreach (explode(',', $group[2]) as $part) {
                        self::remember($imports, $group[1], trim($part));
                    }
                    continue;
                }
                self::remember($imports, '', $clause);
            }
        }

        return $imports;
    }

    /**
     * @param array<string, string> $imports
     */
    private static function remember(array &$imports, string $prefix, string $part): void
    {
        if ($part === '') {
            return;
        }
        if (preg_match('/(.+?)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $part, $alias) === 1) {
            $full = ltrim($alias[1], '\\');
            if ($prefix !== '') {
                $full = $prefix . '\\' . $full;
            }
            $imports[$alias[2]] = $full;
        } else {
            $full = ltrim($part, '\\');
            if ($prefix !== '') {
                $full = $prefix . '\\' . $full;
            }
            $short = str_contains($full, '\\') ? substr($full, strrpos($full, '\\') + 1) : $full;
            $imports[$short] = $full;
        }
    }
}
