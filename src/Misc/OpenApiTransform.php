<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

final class OpenApiTransform
{
    /**
     * @param array<mixed> $schema
     * @param string|int|null $parent
     */
    public static function transformTypes(array &$schema, $parent = null): void
    {
        foreach ($schema as $key => &$value) {
            if ($key === 'type' && is_array($value) && $parent !== 'properties') {
                if (count($value) > 1 && in_array('null', $value, true)) {
                    unset($value[array_search('null', $value, true)]);
                    $schema['nullable'] = true;
                }

                if (count($value) === 1) {
                    $value = implode(',', TypeHelper::toStringList($value));
                } elseif (count($value) > 1) {
                    $oneOf = isset($schema['oneOf']) && is_array($schema['oneOf']) ? $schema['oneOf'] : [];
                    foreach ($value as $type) {
                        $oneOf[] = ['type' => $type];
                    }

                    $schema['oneOf'] = $oneOf;

                    unset($schema['type']);
                }
            } elseif (is_array($value)) {
                self::transformTypes($value, $key);
            }
        }
    }
}
