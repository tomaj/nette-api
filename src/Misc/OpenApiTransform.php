<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Misc;

class OpenApiTransform
{
    /**
     * @param string|int|null $parent
     */
    public static function transformTypes(array &$schema, $parent = null): void
    {
        foreach ($schema as $key => &$value) {
            if ($key === 'type' && is_array($value) && $parent !== 'properties') {
                if (count($value) > 1 && in_array('null', $value, true)) {
                    unset($value[array_search('null', $value)]);
                    $schema['nullable'] = true;
                }
                if (count($value) === 1) {
                    $value = implode(',', $value);
                } elseif (count($value) > 1) {
                    foreach ($schema['type'] as $type) {
                        $schema['oneOf'][] = ['type' => $type];
                    }
                    unset($schema['type']);
                }
            } elseif (is_array($value)) {
                self::transformTypes($value, $key);
            }
        }
    }
}
