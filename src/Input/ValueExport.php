<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

/**
 * Turns defaults, enum cases and constraint arguments into plain values the compiled container can store.
 */
final class ValueExport
{
    public static function export(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return ['__netteApi' => 'enum', 'class' => $value::class, 'value' => $value->value];
        }
        if (!is_array($value)) {
            if (is_object($value)) {
                throw new \LogicException(sprintf(
                    'A %s cannot be stored in the compiled container. Use null, a scalar, an array or a backed enum as a DTO default.',
                    $value::class,
                ));
            }

            return $value;
        }

        $exported = [];
        foreach ($value as $key => $item) {
            $exported[$key] = self::export($item);
        }

        return $exported;
    }

    public static function import(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (($value['__netteApi'] ?? null) === 'enum' && is_string($value['class'] ?? null) && (is_int($value['value'] ?? null) || is_string($value['value'] ?? null))) {
            $enum = $value['class'];
            $case = $enum::tryFrom($value['value']);
            if (!$case instanceof \BackedEnum) {
                throw new \LogicException(sprintf("Cannot restore the default of backed enum %s from '%s'.", $enum, (string) $value['value']));
            }

            return $case;
        }

        $imported = [];
        foreach ($value as $key => $item) {
            $imported[$key] = self::import($item);
        }

        return $imported;
    }
}
