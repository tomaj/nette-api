<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Tomaj\NetteApi\Validation\Email;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;
use Tomaj\NetteApi\Validation\Range;

/**
 * OpenAPI schema of one DTO property. JSON schema here is only an output format.
 */
final class SchemaBuilder
{
    /**
     * @return array<string, mixed>
     */
    public static function build(Field $field): array
    {
        if ($field->casterSchema !== null) {
            $schema = $field->casterSchema;
        } elseif ($field->item instanceof Field) {
            $schema = ['type' => 'array', 'items' => self::build($field->item)];
        } elseif ($field->children !== []) {
            $schema = self::object($field->children);
        } elseif ($field->enumClass !== null) {
            $schema = ['type' => $field->enumInt ? 'integer' : 'string', 'enum' => $field->enumValues];
        } elseif ($field->typeName === FileUpload::class) {
            $schema = ['type' => 'string', 'format' => 'binary'];
        } elseif ($field->typeName === \DateTimeImmutable::class) {
            $schema = ['type' => 'string', 'format' => 'date-time'];
        } else {
            $schema = ['type' => self::scalarType($field->typeName)];
        }

        if ($field->casterSchema === null) {
            foreach ($field->constraints as $constraint) {
                if ($constraint instanceof Range) {
                    if ($constraint->min !== null) {
                        $schema['minimum'] = $constraint->min;
                    }
                    if ($constraint->max !== null) {
                        $schema['maximum'] = $constraint->max;
                    }
                } elseif ($constraint instanceof Length && $field->typeName === 'array') {
                    if ($constraint->min !== null) {
                        $schema['minItems'] = $constraint->min;
                    }
                    if ($constraint->max !== null) {
                        $schema['maxItems'] = $constraint->max;
                    }
                } elseif ($constraint instanceof Length) {
                    if ($constraint->min !== null) {
                        $schema['minLength'] = $constraint->min;
                    }
                    if ($constraint->max !== null) {
                        $schema['maxLength'] = $constraint->max;
                    }
                } elseif ($constraint instanceof Pattern) {
                    $schema['pattern'] = $constraint->expression();
                } elseif ($constraint instanceof Email) {
                    $schema['format'] = 'email';
                }
            }
        }

        if ($field->nullable) {
            $schema['nullable'] = true;
        }
        if ($field->hasDefault && $field->default !== null) {
            $schema['default'] = self::wire($field->default);
        }
        if ($field->examples !== []) {
            $examples = $field->examples;
            $schema['example'] = self::wire(reset($examples));
        }

        return $schema;
    }

    /**
     * @param list<Field> $fields
     * @return array<string, mixed>
     */
    public static function object(array $fields): array
    {
        $properties = [];
        $required = [];
        foreach ($fields as $field) {
            $properties[$field->externalName] = self::build($field);
            if ($field->description !== null && $field->description !== '') {
                $properties[$field->externalName]['description'] = $field->description;
            }
            if ($field->required()) {
                $required[] = $field->externalName;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if ($required !== []) {
            $schema['required'] = $required;
        }

        return $schema;
    }

    public static function wire(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if (is_array($value)) {
            $wired = [];
            foreach ($value as $key => $item) {
                $wired[$key] = self::wire($item);
            }

            return $wired;
        }

        return $value;
    }

    private static function scalarType(string $type): string
    {
        return match ($type) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            default => 'string',
        };
    }
}
