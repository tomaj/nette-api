<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Params\MappedInputParam;

/**
 * Compiled description of one typed handler. Requests reuse it instead of reflecting again.
 */
final readonly class DtoMetadata
{
    /**
     * @param list<Field> $fields root properties, in constructor order
     * @param list<string> $contentTypes
     */
    public function __construct(
        public string $handler,
        public string $class,
        public array $fields,
        public array $contentTypes,
        public bool $bodyRequired,
        public bool $readsBody,
        public ?string $firstBody,
    ) {
    }

    /**
     * @return list<InputParam>
     */
    public function inputParams(): array
    {
        $mapper = new DtoMapper($this);
        $params = [];
        foreach ($this->fields as $field) {
            $params[] = new MappedInputParam($field, $mapper, $this);
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $values values keyed by the external name, already converted
     */
    public function instantiate(array $values): object
    {
        return self::construct($this->class, $this->fields, static fn (Field $field): mixed => $values[$field->externalName]);
    }

    /**
     * Creates a DTO with each field's value passed as the named constructor argument.
     *
     * @param list<Field> $fields
     * @param \Closure(Field): mixed $value
     */
    public static function construct(string $class, array $fields, \Closure $value): object
    {
        $arguments = [];
        foreach ($fields as $field) {
            $arguments[$field->property] = $value($field);
        }

        return new $class(...$arguments);
    }

    /**
     * @return list<string>
     */
    public function pathNames(): array
    {
        $names = [];
        foreach ($this->fields as $field) {
            if ($field->source === Source::Path) {
                $names[] = $field->externalName;
            }
        }

        return $names;
    }
}
