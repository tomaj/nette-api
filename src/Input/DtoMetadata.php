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
     * @param list<string> $files absolute paths of the handler and DTO files, so editing one rebuilds the container
     */
    public function __construct(
        public string $handler,
        public string $class,
        public array $fields,
        public array $contentTypes,
        public bool $bodyRequired,
        public bool $readsBody,
        public ?string $firstBody,
        public array $files,
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
        $arguments = [];
        foreach ($this->fields as $field) {
            $arguments[$field->property] = $values[$field->externalName];
        }

        return new ($this->class)(...$arguments);
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

    /**
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return [
            'handler' => $this->handler,
            'class' => $this->class,
            'fields' => array_map(static fn (Field $field): array => $field->export(), $this->fields),
            'contentTypes' => $this->contentTypes,
            'bodyRequired' => $this->bodyRequired,
            'readsBody' => $this->readsBody,
            'firstBody' => $this->firstBody,
            'files' => $this->files,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function import(array $data): self
    {
        $fields = [];
        $exportedFields = $data['fields'] ?? null;
        if (!is_array($exportedFields)) {
            throw new \LogicException('Compiled DTO metadata has no fields. Rebuild the DI container.');
        }
        foreach ($exportedFields as $field) {
            if (!is_array($field)) {
                throw new \LogicException('Compiled DTO metadata has a field that is not an array. Rebuild the DI container.');
            }
            $fields[] = Field::import(self::stringKeyed($field));
        }

        return new self(
            is_string($data['handler']) ? $data['handler'] : '',
            is_string($data['class']) ? $data['class'] : '',
            $fields,
            is_array($data['contentTypes']) ? array_values(array_filter($data['contentTypes'], is_string(...))) : [],
            (bool) $data['bodyRequired'],
            (bool) $data['readsBody'],
            is_string($data['firstBody']) ? $data['firstBody'] : null,
            is_array($data['files']) ? array_values(array_filter($data['files'], is_string(...))) : [],
        );
    }

    /**
     * @param array<mixed> $data
     * @return array<array-key, mixed>
     */
    private static function stringKeyed(array $data): array
    {
        $keyed = [];
        foreach ($data as $key => $value) {
            $keyed[$key] = $value;
        }

        return $keyed;
    }
}
