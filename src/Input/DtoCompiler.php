<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Input;

use Tomaj\NetteApi\Handlers\BaseHandler;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tomaj\NetteApi\Validation\ConstraintContext;
use Tomaj\NetteApi\Validation\ConstraintInterface;
use Tomaj\NetteApi\Validation\Email;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;
use Tomaj\NetteApi\Validation\Range;

/**
 * Reflects a TypedHandler and its DTO once. Invalid declarations throw LogicException
 * with the class, the property, what is wrong and how to fix it.
 */
final class DtoCompiler
{
    /** @var array<string, string> */
    private array $files = [];

    /** @var list<string> */
    private array $stack = [];

    public static function compile(string $handler): DtoMetadata
    {
        return new self()->handler($handler);
    }

    private function handler(string $handler): DtoMetadata
    {
        if (!class_exists($handler)) {
            $this->fail(sprintf(
                "Typed handler '%s' does not exist. Check the class name and the autoload section of composer.json.",
                $handler,
            ));
        }
        if (!is_a($handler, TypedHandler::class, true)) {
            $this->fail(sprintf(
                '%s must extend %s to use a typed input DTO. A handler that implements handle(array $params) stays on %s.',
                $handler,
                TypedHandler::class,
                BaseHandler::class,
            ));
        }

        $reflection = new \ReflectionClass($handler);
        $this->touch($reflection);
        $dto = $this->dtoClass($reflection);
        $fields = $this->dto($dto, true);
        $contentTypes = $this->contentTypes($reflection, $fields);
        $readsBody = false;
        $bodyRequired = false;
        $firstBody = null;
        foreach ($fields as $field) {
            if (!$field->readsBody()) {
                continue;
            }
            $readsBody = true;
            $firstBody ??= $field->property;
            if ($field->required()) {
                $bodyRequired = true;
            }
        }

        return new DtoMetadata($handler, $dto, $fields, $contentTypes, $bodyRequired, $readsBody, $firstBody, array_values($this->files));
    }

    /** @param \ReflectionClass<*> $handler */
    private function dtoClass(\ReflectionClass $handler): string
    {
        if (!$handler->hasMethod('handleDto')) {
            $this->fail(sprintf(
                '%s must implement public function handleDto(SomeInput $input): ResponseInterface. TypedHandler reads the DTO class from that parameter.',
                $handler->getName(),
            ));
        }

        $method = $handler->getMethod('handleDto');
        if (!$method->isPublic() || $method->isStatic()) {
            $this->fail(sprintf(
                '%s::handleDto() must be a public instance method with one DTO parameter, for example public function handleDto(ListUsersInput $input): ResponseInterface.',
                $handler->getName(),
            ));
        }

        $parameters = $method->getParameters();
        if (count($parameters) !== 1 || $parameters[0]->isVariadic()) {
            $this->fail(sprintf(
                '%s::handleDto() must declare exactly one parameter, the input DTO. It has %d. Example: public function handleDto(ListUsersInput $input): ResponseInterface.',
                $handler->getName(),
                count($parameters),
            ));
        }

        $parameter = $parameters[0];
        if ($parameter->allowsNull() || $parameter->isOptional()) {
            $this->fail(sprintf(
                '%s::handleDto($%s) must be a non-nullable DTO without a default. The request always produces the object; nullability belongs on the DTO properties.',
                $handler->getName(),
                $parameter->getName(),
            ));
        }

        $type = $parameter->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            $this->fail(sprintf(
                '%s::handleDto($%s) must be type-hinted with the DTO class, for example handleDto(ListUsersInput $input). Unions, scalars and missing types are not an input DTO.',
                $handler->getName(),
                $parameter->getName(),
            ));
        }

        $return = $method->getReturnType();
        if (!$return instanceof \ReflectionNamedType || $return->isBuiltin() || !is_a($return->getName(), ResponseInterface::class, true)) {
            $this->fail(sprintf(
                '%s::handleDto() must return %s (or a subtype). Add `: ResponseInterface` so the response is the same type BaseHandler::handle() returns.',
                $handler->getName(),
                ResponseInterface::class,
            ));
        }

        return $type->getName();
    }

    /**
     * @param \ReflectionClass<*> $handler
     * @param list<Field> $fields
     * @return list<string>
     */
    private function contentTypes(\ReflectionClass $handler, array $fields): array
    {
        $attributes = $handler->getAttributes(Consumes::class);
        if (count($attributes) > 1) {
            $this->fail(sprintf(
                '%s has more than one #[Consumes]. Declare every media type on a single attribute, for example #[Consumes(\'application/json\')].',
                $handler->getName(),
            ));
        }

        $declared = null;
        if ($attributes !== []) {
            try {
                $consumes = $attributes[0]->newInstance();
            } catch (\Throwable $exception) {
                $this->fail(sprintf(
                    '%s #[Consumes] is invalid: %s. Pass one or more media types, for example #[Consumes(\'application/json\')].',
                    $handler->getName(),
                    $exception->getMessage(),
                ));
            }
            if ($consumes->types === []) {
                $this->fail(sprintf(
                    '%s #[Consumes] needs at least one media type, for example #[Consumes(\'application/json\')].',
                    $handler->getName(),
                ));
            }
            $declared = [];
            foreach ($consumes->types as $type) {
                $normalized = MediaTypes::normalize($type);
                if (!in_array($normalized, MediaTypes::ALL, true)) {
                    $this->fail(sprintf(
                        "%s #[Consumes] has '%s', which a typed body cannot read. Use one of: %s.",
                        $handler->getName(),
                        $type,
                        implode(', ', MediaTypes::ALL),
                    ));
                }
                if (in_array($normalized, $declared, true)) {
                    $this->fail(sprintf(
                        "%s #[Consumes] lists '%s' twice. List each media type once.",
                        $handler->getName(),
                        $normalized,
                    ));
                }
                $declared[] = $normalized;
            }
        }

        $hasFile = self::containsFile($fields);
        $types = $declared ?? ($hasFile
            ? [MediaTypes::JSON, MediaTypes::MULTIPART]
            : [MediaTypes::JSON, MediaTypes::FORM]);
        if ($hasFile && !in_array(MediaTypes::MULTIPART, $types, true)) {
            $this->fail(sprintf(
                '%s has a #[File] property but does not accept multipart/form-data, so the file cannot be uploaded. Add it to #[Consumes], or remove #[Consumes] to accept application/json and multipart/form-data.',
                $handler->getName(),
            ));
        }

        return $types;
    }

    /**
     * @return list<Field>
     */
    private function dto(string $class, bool $root): array
    {
        if (in_array($class, $this->stack, true)) {
            $this->fail(sprintf(
                'DTO %s refers to itself (%s). Nested DTOs cannot form a cycle; keep the nesting one way.',
                $class,
                implode(' -> ', [...$this->stack, $class]),
            ));
        }
        if (!class_exists($class) && !interface_exists($class) && !enum_exists($class)) {
            $this->fail(sprintf(
                "DTO '%s' does not exist. Use a readonly class in the same project, and check the namespace and the use statement.",
                $class,
            ));
        }

        $this->stack[] = $class;
        $reflection = new \ReflectionClass($class);
        $this->touch($reflection);
        if (!$reflection->isInstantiable()) {
            $this->fail(sprintf(
                '%s cannot be an input DTO. Use a concrete readonly class with a constructor, not an enum, an interface or an abstract class.',
                $class,
            ));
        }

        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            $this->fail(sprintf(
                '%s needs a constructor with promoted properties, for example public function __construct(#[Query] public int $page = 1).',
                $class,
            ));
        }

        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isVariadic() || $parameter->isPassedByReference()) {
                $this->fail(sprintf(
                    '%s::__construct() parameter $%s is %s. A DTO constructor takes only promoted properties, for example public function __construct(#[Query] public int $page = 1).',
                    $class,
                    $parameter->getName(),
                    $parameter->isVariadic() ? 'variadic' : 'passed by reference',
                ));
            }
            if (!$parameter->isPromoted() && !$parameter->isOptional()) {
                $this->fail(sprintf(
                    '%s::__construct() has the required parameter $%s that is not a promoted property, so the mapper cannot pass it. Make it a promoted property with a source attribute, for example #[Body] public string $%s, give it a default, or move it out of the DTO.',
                    $class,
                    $parameter->getName(),
                    $parameter->getName(),
                ));
            }
        }

        $properties = [];
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                $this->fail(sprintf('%s::$%s is static. A DTO property has to be a promoted constructor parameter.', $class, $property->getName()));
            }
            $properties[] = $property;
        }
        if ($properties === []) {
            $this->fail(sprintf('%s has no properties. Add the inputs as promoted constructor properties with #[Query], #[Path], #[Header], #[Body] or #[File].', $class));
        }

        $fields = [];
        $names = [];
        foreach ($properties as $property) {
            $field = $this->property($property, $reflection, $root);
            if (isset($names[$field->externalName])) {
                $this->fail(sprintf(
                    "%s properties \$%s and \$%s both use the external name '%s'. Each input name can be declared once; pass a different name, for example #[Query('pageSize')].",
                    $class,
                    $names[$field->externalName],
                    $field->property,
                    $field->externalName,
                ));
            }
            $names[$field->externalName] = $field->property;
            $fields[] = $field;
        }
        $this->checkDefaults($class, $fields);
        array_pop($this->stack);

        return $fields;
    }

    /** @param \ReflectionClass<*> $dto */
    private function property(\ReflectionProperty $property, \ReflectionClass $dto, bool $root): Field
    {
        $class = $dto->getName();
        $name = $property->getName();
        if (!$property->isPromoted()) {
            $this->fail(sprintf(
                '%s::$%s is not a promoted constructor property. Declare it in the constructor: public function __construct(#[Body] public string $%s).',
                $class,
                $name,
                $name,
            ));
        }
        if (!$property->isReadOnly()) {
            $this->fail(sprintf(
                '%s::$%s must be readonly. Declare `readonly class %s` or `public readonly` on $%s, so the mapper creates the value once.',
                $class,
                $name,
                $dto->getShortName(),
                $name,
            ));
        }

        $parameter = null;
        $constructor = $dto->getConstructor();
        foreach ($constructor instanceof \ReflectionMethod ? $constructor->getParameters() : [] as $candidate) {
            if ($candidate->getName() === $name) {
                $parameter = $candidate;
            }
        }
        if (!$parameter instanceof \ReflectionParameter) {
            $this->fail(sprintf('%s::$%s is missing from the constructor. Promoted properties are declared as constructor parameters.', $class, $name));
        }

        [$typeName, $builtin, $nullable] = $this->type($property, $dto);
        $source = $this->source($property, $dto, $root);
        $external = $this->externalName($source['attribute'], $name, $class);
        $list = $this->list($property, $dto, $typeName);
        $hasDefault = $parameter->isDefaultValueAvailable();
        if ($source['source'] === Source::Path && $nullable) {
            $this->fail(sprintf(
                '%s::$%s is a path parameter and cannot be nullable. A {name} segment is always present. Use `public int $%s` and put {%s} in the endpoint URL.',
                $class,
                $name,
                $name,
                $external,
            ));
        }
        if ($source['source'] === Source::Path && $hasDefault) {
            $this->fail(sprintf(
                '%s::$%s is a path parameter and cannot have a default. Remove the default and put {%s} in the endpoint URL, for example #[Get(1, \'users\', \'{%s}/detail\')].',
                $class,
                $name,
                $external,
                $external,
            ));
        }

        $default = null;
        if ($hasDefault) {
            $default = $parameter->getDefaultValue();
            $this->checkDefaultValue($class, $name, $default);
        }

        $enumClass = null;
        $enumInt = false;
        $enumValues = [];
        if (!$builtin && enum_exists($typeName)) {
            if (!is_a($typeName, \BackedEnum::class, true)) {
                $this->fail(sprintf(
                    '%s::$%s uses %s, which is not a backed enum. Give the enum a string or int backing (`enum Status: string`) or accept the backing value as a string.',
                    $class,
                    $name,
                    $typeName,
                ));
            }
            $enumClass = $typeName;
            $this->touch(new \ReflectionEnum($typeName));
            $backing = new \ReflectionEnum($typeName)->getBackingType();
            $enumInt = $backing instanceof \ReflectionNamedType && $backing->getName() === 'int';
            $enumValues = ScalarCoercer::enumValues($typeName);
        }

        // a caster replaces the built-in conversion, so its result type is not compiled as a transport DTO
        $casted = $property->getAttributes(Cast::class) !== [];
        $children = [];
        $item = null;
        $listItem = null;
        $listItemBuiltin = false;
        if ($typeName === 'array') {
            if ($list === null) {
                $found = $this->documentedType($property, $dto);
                $this->fail(sprintf(
                    '%s::$%s is an array without an item type. Add `/** @param list<ItemInput> $%s */` (or list<string>, list<int>, list<float>, list<bool>, list<FileUpload>). Associative and untyped arrays are rejected.%s',
                    $class,
                    $name,
                    $name,
                    $found === null ? '' : sprintf(' Found `%s`, but only list<T>, non-empty-list<T> and T[] are read.', $found),
                ));
            }
            $listItem = $list['name'];
            $listItemBuiltin = $list['builtin'];
            $item = $casted ? null : $this->itemField($list, $source['source'], $class, $name, $root);
        } elseif (!$casted && !$builtin && $enumClass === null && $typeName !== \DateTimeImmutable::class && $typeName !== FileUpload::class) {
            $children = $this->dto($typeName, false);
        }

        if (!$casted && ($listItem !== null || $children !== []) && in_array($source['source'], [Source::Path, Source::Header], true)) {
            $this->fail(sprintf(
                "%s::\$%s reads #[%s], which is one string, so it cannot be %s. Use a scalar type or split the string, for example #[Cast(Split::class, ',')].",
                $class,
                $name,
                new \ReflectionClass($source['attribute'])->getShortName(),
                $listItem !== null ? sprintf('list<%s>', $listItem) : $typeName,
            ));
        }
        if (!$casted && $source['source'] === Source::Query && ($children !== [] || ($item instanceof Field && $item->children !== []))) {
            $this->fail(sprintf(
                '%s::$%s reads #[Query], which carries flat values, so it cannot be %s. Read nested objects from the body with #[Body], or use a scalar, an enum or a list of them in the query.',
                $class,
                $name,
                $listItem !== null ? sprintf('list<%s>', $listItem) : $typeName,
            ));
        }
        if ($source['source'] === Source::File && $typeName !== FileUpload::class && $listItem !== FileUpload::class) {
            $this->fail(sprintf(
                '%s::$%s uses #[File] but its type is %s. Use FileUpload, ?FileUpload or list<FileUpload>.',
                $class,
                $name,
                $typeName === 'array' ? sprintf('list<%s>', (string) $listItem) : $typeName,
            ));
        }
        if ($source['source'] !== Source::File && ($typeName === FileUpload::class || $listItem === FileUpload::class)) {
            $this->fail(sprintf('%s::$%s is a FileUpload, so it needs #[File], not #[%s].', $class, $name, new \ReflectionClass($source['attribute'])->getShortName()));
        }

        $constraints = $this->constraints($property, $dto, $typeName);
        $cast = $this->caster($property, $dto, $typeName, $builtin, $listItem, $listItemBuiltin);
        $this->checkListDefault($class, $name, $default, $listItem, $listItemBuiltin);

        return new Field(
            property: $name,
            externalName: $external,
            source: $source['source'],
            typeName: $typeName,
            builtin: $builtin,
            nullable: $nullable,
            hasDefault: $hasDefault,
            default: $default,
            description: $source['attribute']->description,
            examples: $this->examples($source['attribute'], $class, $name),
            listItem: $listItem,
            listItemBuiltin: $listItemBuiltin,
            enumClass: $enumClass,
            enumInt: $enumInt,
            enumValues: $enumValues,
            constraints: $constraints['instances'],
            constraintSpecs: $constraints['specs'],
            casterClass: $cast['class'],
            casterArguments: $cast['arguments'],
            casterSchema: $cast['schema'],
            children: $children,
            item: $item,
            dtoClass: $class,
        );
    }

    /**
     * @param \ReflectionClass<*> $dto
     * @return array{0: string, 1: bool, 2: bool}
     */
    private function type(\ReflectionProperty $property, \ReflectionClass $dto): array
    {
        $type = $property->getType();
        $class = $dto->getName();
        $name = $property->getName();
        if (!$type instanceof \ReflectionType) {
            $this->fail(sprintf(
                '%s::$%s has no type. Declare a concrete type such as string, int, a backed enum, DateTimeImmutable or another readonly DTO.',
                $class,
                $name,
            ));
        }

        $nullable = $type->allowsNull();
        if ($type instanceof \ReflectionUnionType) {
            $this->fail(sprintf(
                '%s::$%s has type %s. Typed input supports one type, or a nullable type (`?int` or `int|null`), not a union.',
                $class,
                $name,
                (string) $type,
            ));
        }
        if (!$type instanceof \ReflectionNamedType) {
            $this->fail(sprintf('%s::$%s has type %s. Intersection types are not supported; use one class.', $class, $name, (string) $property->getType()));
        }

        $typeName = $type->getName();
        if ($typeName === 'self') {
            $typeName = $class;
        } elseif ($typeName === 'parent' && $dto->getParentClass() instanceof \ReflectionClass) {
            $typeName = $dto->getParentClass()->getName();
        }
        if (in_array($typeName, ['mixed', 'object', 'null'], true)) {
            $this->fail(sprintf(
                '%s::$%s has type %s, which has no contract to validate. Declare string, int, float, bool, a backed enum, DateTimeImmutable, FileUpload or a DTO.',
                $class,
                $name,
                $typeName,
            ));
        }
        if (is_a($typeName, \DateTimeInterface::class, true) && $typeName !== \DateTimeImmutable::class) {
            $this->fail(sprintf(
                '%s::$%s has type %s. Use DateTimeImmutable itself, the only date type typed input supports (RFC 3339 text, for example 2024-05-01T10:00:00Z); a subclass or an interface cannot be built from the input.',
                $class,
                $name,
                $typeName,
            ));
        }
        if ($type->isBuiltin() && !in_array($typeName, ['int', 'float', 'bool', 'string', 'array'], true)) {
            $this->fail(sprintf(
                '%s::$%s has type %s. Use string, int, float, bool or list<T>.',
                $class,
                $name,
                $typeName,
            ));
        }

        return [$typeName, $type->isBuiltin(), $nullable];
    }

    /**
     * @param \ReflectionClass<*> $dto
     * @return array{source: Source, attribute: Query|Path|Header|Body|File}
     */
    private function source(\ReflectionProperty $property, \ReflectionClass $dto, bool $root): array
    {
        $found = [];
        foreach ($property->getAttributes() as $attribute) {
            $name = $attribute->getName();
            if (!is_a($name, Query::class, true) && !is_a($name, Path::class, true) && !is_a($name, Header::class, true) && !is_a($name, Body::class, true) && !is_a($name, File::class, true)) {
                continue;
            }
            try {
                $instance = $attribute->newInstance();
            } catch (\Throwable $exception) {
                $this->fail(sprintf(
                    '%s::$%s attribute %s is invalid: %s.',
                    $dto->getName(),
                    $property->getName(),
                    new \ReflectionClass($name)->getShortName(),
                    $exception->getMessage(),
                ));
            }
            $found[] = $this->sourceAttribute($instance);
        }
        if ($found === []) {
            $this->fail(sprintf(
                '%s::$%s has no source attribute. Add exactly one of #[Query], #[Path], #[Header], #[Body] or #[File].',
                $dto->getName(),
                $property->getName(),
            ));
        }
        if (count($found) > 1) {
            $this->fail(sprintf(
                '%s::$%s has more than one source attribute. Keep exactly one; a value comes from one place.',
                $dto->getName(),
                $property->getName(),
            ));
        }

        $attribute = $found[0];
        $source = match (true) {
            $attribute instanceof Query => Source::Query,
            $attribute instanceof Path => Source::Path,
            $attribute instanceof Header => Source::Header,
            $attribute instanceof Body => Source::Body,
            $attribute instanceof File => Source::File,
        };
        if (!$root && $source === Source::File) {
            $this->fail(sprintf(
                'Nested DTO %s::$%s uses #[File]. Uploads are read only on the root DTO; move the property to the input DTO of the handler, for example #[File] public FileUpload $%s.',
                $dto->getName(),
                $property->getName(),
                $property->getName(),
            ));
        }
        if (!$root && $source !== Source::Body) {
            $this->fail(sprintf(
                'Nested DTO %s::$%s uses #[%s]. Nested properties are part of the body: use #[Body]. Query, path and header belong on the root DTO.',
                $dto->getName(),
                $property->getName(),
                new \ReflectionClass($attribute)->getShortName(),
            ));
        }

        return ['source' => $source, 'attribute' => $attribute];
    }

    private function sourceAttribute(object $instance): Query|Path|Header|Body|File
    {
        if ($instance instanceof Query) {
            return $instance;
        }
        if ($instance instanceof Path) {
            return $instance;
        }
        if ($instance instanceof Header) {
            return $instance;
        }
        if ($instance instanceof Body) {
            return $instance;
        }
        if ($instance instanceof File) {
            return $instance;
        }

        throw new \LogicException(sprintf('Attribute %s is not an input source. Use #[Query], #[Path], #[Header], #[Body] or #[File].', $instance::class));
    }

    private function externalName(Query|Path|Header|Body|File $attribute, string $property, string $class): string
    {
        $name = $attribute->name ?? $property;
        if ($name === '') {
            $this->fail(sprintf(
                "%s::$%s has an empty input name. Omit the name to use the property, or pass it, for example #[%s('%s')].",
                $class,
                $property,
                new \ReflectionClass($attribute)->getShortName(),
                $attribute instanceof Header ? 'X-Request-Id' : 'my_name',
            ));
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_-]*$/', $name) !== 1) {
            $this->fail(sprintf(
                "%s::$%s input name '%s' must start with a letter or an underscore, and have only letters, digits, underscores and dashes (for example X-Request-Id). Dots and brackets are reserved for error paths.",
                $class,
                $property,
                $name,
            ));
        }

        if ($attribute instanceof Path && preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            $this->fail(sprintf(
                "%s::$%s path name '%s' is written as a {%s} segment of the URL, so it can only have letters, digits and underscores. Use for example #[Path('%s')].",
                $class,
                $property,
                $name,
                $name,
                str_replace('-', '_', $name),
            ));
        }
        if ($attribute instanceof Header && str_contains($name, '_')) {
            $this->fail(sprintf(
                "%s::$%s header name '%s' has an underscore, but the request exposes header names with dashes, so it would never match. Use #[Header('%s')].",
                $class,
                $property,
                $name,
                str_replace('_', '-', $name),
            ));
        }

        return $name;
    }

    /**
     * @return array<string, mixed>
     */
    private function examples(Query|Path|Header|Body|File $attribute, string $class, string $property): array
    {
        $examples = [];
        foreach ($attribute->examples as $name => $example) {
            if (!is_string($name)) {
                $this->fail(sprintf(
                    "%s::$%s examples must be a map of name to value, for example #[Query(examples: ['first page' => 1])]. A list was given.",
                    $class,
                    $property,
                ));
            }
            $examples[$name] = $example;
        }

        return $examples;
    }

    /**
     * @param \ReflectionClass<*> $dto
     * @return array{name: string, builtin: bool}|null
     */
    private function list(\ReflectionProperty $property, \ReflectionClass $dto, string $typeName): ?array
    {
        $documented = $this->documentedType($property, $dto);
        if ($documented === null) {
            return null;
        }

        // nullability comes from the native type, so `?list<T>`, `list<T>|null` and `null|list<T>` mean list<T>
        $type = preg_replace('/^\?\s*|\s*\|\s*null$|^null\s*\|\s*/i', '', $documented) ?? $documented;
        $item = null;
        if (preg_match('/^(?:non-empty-)?list\s*<\s*(.+?)\s*>$/', $type, $match) === 1 || preg_match('/^([A-Za-z_\\\\][A-Za-z0-9_\\\\]*)\[\]$/', $type, $match) === 1) {
            $item = $match[1];
        }
        if ($item === null) {
            if ($typeName === 'array' && preg_match('/^array\b/', $type) === 1) {
                $this->fail(sprintf(
                    '%s::$%s documents `%s`, an array that is not a list. Use `list<Item>` so the item type is explicit; associative arrays are not supported.',
                    $dto->getName(),
                    $property->getName(),
                    $documented,
                ));
            }

            return null;
        }
        if ($typeName !== 'array') {
            $this->fail(sprintf(
                '%s::$%s documents list<%s> but its PHP type is %s. Change the property type to array.',
                $dto->getName(),
                $property->getName(),
                $item,
                $typeName,
            ));
        }
        if (preg_match('/^(?:non-empty-)?(?:list|array)\b|[|?&]/', $item) === 1) {
            $this->fail(sprintf(
                '%s::$%s documents list<%s>. A list item is one non-nullable type: use list<string>, list<int>, list<float>, list<bool>, list<FileUpload> or list<ItemInput>. For nested lists or optional items, declare an item DTO.',
                $dto->getName(),
                $property->getName(),
                $item,
            ));
        }
        if (preg_match('/<|^[a-z]+(?:-[a-z]+)+$/', $item) === 1) {
            $this->fail(sprintf(
                '%s::$%s documents list<%s>. PHPStan refinements such as positive-int, non-empty-string or int<0, 10> and generic items are not read: use list<int>, list<string>, list<float>, list<bool>, list<FileUpload> or list<ItemInput>, and add a constraint such as #[Range] for the rest.',
                $dto->getName(),
                $property->getName(),
                $item,
            ));
        }

        $resolved = TypeName::resolve($item, $dto);
        $builtin = in_array($resolved, ['string', 'int', 'float', 'bool'], true);
        if (!$builtin && !class_exists($resolved)) {
            $this->fail(sprintf(
                '%s::$%s documents list<%s>, and that type was not found. Use a full name, import it, or use list<string>, list<int>, list<float>, list<bool> or list<FileUpload>.',
                $dto->getName(),
                $property->getName(),
                $item,
            ));
        }

        return ['name' => $resolved, 'builtin' => $builtin];
    }

    /**
     * The documented type of a property: `@param` of the constructor or the property, `@var` of the property.
     * `@phpstan-param` and `@psalm-param` win over `@param`, because they are the more precise ones.
     *
     * @param \ReflectionClass<*> $dto
     */
    private function documentedType(\ReflectionProperty $property, \ReflectionClass $dto): ?string
    {
        $propertyDoc = $property->getDocComment() ?: '';
        $constructor = $dto->getConstructor();
        $constructorDoc = $constructor instanceof \ReflectionMethod ? ($constructor->getDocComment() ?: '') : '';
        $doc = sprintf("%s\n%s", $propertyDoc, $constructorDoc);
        $name = $property->getName();

        foreach (['(?:phpstan|psalm)-param', 'param'] as $tag) {
            if (preg_match('/@' . $tag . '\s+([^$]*?)\s*\$' . $name . '\b/s', $doc, $match) === 1 && $match[1] !== '') {
                return $match[1];
            }
        }
        // one type, possibly with spaces inside its brackets, and then maybe a description: `@var list<string> Names`
        if (preg_match('/@(?:phpstan-|psalm-)?var\s+((?:[^\s<>{}()*]|(?<b>[<{(](?:[^<>{}()]|(?&b))*[>})]))+)/', $propertyDoc, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /**
     * @param array{name: string, builtin: bool} $list
     */
    private function itemField(array $list, Source $source, string $class, string $property, bool $root): Field
    {
        $itemName = $list['name'];
        $builtin = $list['builtin'];
        $children = [];
        $enumClass = null;
        $enumInt = false;
        $enumValues = [];
        if (!$builtin && is_a($itemName, \BackedEnum::class, true)) {
            $enumClass = $itemName;
            $this->touch(new \ReflectionEnum($itemName));
            $backing = new \ReflectionEnum($itemName)->getBackingType();
            $enumInt = $backing instanceof \ReflectionNamedType && $backing->getName() === 'int';
            $enumValues = ScalarCoercer::enumValues($itemName);
        } elseif (!$builtin && $itemName !== FileUpload::class && $itemName !== \DateTimeImmutable::class) {
            $children = $this->dto($itemName, false);
        }

        return new Field(
            property: $property,
            externalName: $property,
            source: $source,
            typeName: $itemName,
            builtin: $builtin,
            nullable: false,
            hasDefault: false,
            default: null,
            description: null,
            examples: [],
            listItem: null,
            listItemBuiltin: false,
            enumClass: $enumClass,
            enumInt: $enumInt,
            enumValues: $enumValues,
            constraints: [],
            constraintSpecs: [],
            casterClass: null,
            casterArguments: [],
            casterSchema: null,
            children: $children,
            item: null,
            dtoClass: $class,
        );
    }

    /**
     * @param \ReflectionClass<*> $dto
     * @return array{instances: list<ConstraintInterface>, specs: list<array{class: class-string<ConstraintInterface>, args: array<int|string, mixed>}>}
     */
    private function constraints(\ReflectionProperty $property, \ReflectionClass $dto, string $typeName): array
    {
        $instances = [];
        $specs = [];
        foreach ($property->getAttributes() as $attribute) {
            $name = $attribute->getName();
            if (!is_a($name, ConstraintInterface::class, true)) {
                continue;
            }
            try {
                $instance = $attribute->newInstance();
            } catch (\Throwable $exception) {
                $this->fail(sprintf(
                    '%s::$%s constraint %s is invalid: %s.',
                    $dto->getName(),
                    $property->getName(),
                    new \ReflectionClass($name)->getShortName(),
                    $exception->getMessage(),
                ));
            }
            if (!$instance instanceof ConstraintInterface) {
                $this->fail(sprintf(
                    '%s::$%s attribute %s must implement %s to be used as a constraint.',
                    $dto->getName(),
                    $property->getName(),
                    $name,
                    ConstraintInterface::class,
                ));
            }
            $this->constraintApplies($instance, $typeName, $dto->getName(), $property->getName());
            $this->touch(new \ReflectionClass($instance));
            $arguments = [];
            foreach ($attribute->getArguments() as $key => $argument) {
                $arguments[$key] = $argument;
            }
            $instances[] = $instance;
            $specs[] = ['class' => $instance::class, 'args' => $arguments];
        }

        return ['instances' => $instances, 'specs' => $specs];
    }

    private function constraintApplies(ConstraintInterface $constraint, string $typeName, string $class, string $property): void
    {
        $ok = match (true) {
            $constraint instanceof Range => $typeName === 'int' || $typeName === 'float',
            $constraint instanceof Length => $typeName === 'string' || $typeName === 'array',
            $constraint instanceof Pattern, $constraint instanceof Email => $typeName === 'string',
            default => true,
        };
        if ($ok) {
            return;
        }

        $this->fail(sprintf(
            '%s::$%s has #[%s], which does not apply to %s. %s',
            $class,
            $property,
            new \ReflectionClass($constraint)->getShortName(),
            $typeName,
            match (true) {
                $constraint instanceof Range => 'Use it on int or float.',
                $constraint instanceof Length => 'Use it on string (characters) or a list (items).',
                default => 'Use it on a string.',
            },
        ));
    }

    /**
     * @param \ReflectionClass<*> $dto
     * @return array{class: class-string<CasterInterface>|null, arguments: array<mixed>, schema: array<string, mixed>|null}
     */
    private function caster(\ReflectionProperty $property, \ReflectionClass $dto, string $typeName, bool $builtin, ?string $listItem, bool $listBuiltin): array
    {
        $found = $property->getAttributes(Cast::class);
        if (count($found) > 1) {
            $this->fail(sprintf('%s::$%s has more than one #[Cast]. Keep one caster; it replaces the built-in conversion.', $dto->getName(), $property->getName()));
        }
        if ($found === []) {
            return ['class' => null, 'arguments' => [], 'schema' => null];
        }

        try {
            $cast = $found[0]->newInstance();
        } catch (\Throwable $exception) {
            $this->fail(sprintf('%s::$%s #[Cast] is invalid: %s.', $dto->getName(), $property->getName(), $exception->getMessage()));
        }
        $class = $cast->caster;
        if (!class_exists($class)) {
            $this->fail(sprintf(
                '%s::$%s #[Cast] names %s, which does not exist. Use a class that implements %s, for example #[Cast(%s::class, \'d.m.Y\')].',
                $dto->getName(),
                $property->getName(),
                $class,
                CasterInterface::class,
                DateFormat::class,
            ));
        }

        $reflection = new \ReflectionClass($class);
        $this->touch($reflection);
        if (!$reflection->isInstantiable() || !$reflection->implementsInterface(CasterInterface::class)) {
            $this->fail(sprintf(
                '%s::$%s caster %s must be a concrete class implementing %s.',
                $dto->getName(),
                $property->getName(),
                $class,
                CasterInterface::class,
            ));
        }
        try {
            $instance = $reflection->newInstanceArgs($cast->arguments);
        } catch (\Throwable $exception) {
            $this->fail(sprintf(
                '%s::$%s caster %s cannot be created with the #[Cast] arguments: %s. Its constructor is __construct(%s).',
                $dto->getName(),
                $property->getName(),
                $class,
                $exception->getMessage(),
                implode(', ', array_map(
                    static fn (\ReflectionParameter $parameter): string => $parameter->getType() === null ? '$' . $parameter->getName() : sprintf('%s $%s', (string) $parameter->getType(), $parameter->getName()),
                    $reflection->getConstructor()?->getParameters() ?? [],
                )),
            ));
        }

        $this->casterReturn($reflection, $dto, $property->getName(), $typeName, $builtin, $listItem);
        $schema = null;
        if ($instance instanceof OpenApiCasterInterface) {
            try {
                $hint = $instance->openApiSchema();
            } catch (\Throwable $exception) {
                $this->fail(sprintf('%s::openApiSchema() failed: %s. Return type, format and example of the wire value.', $class, $exception->getMessage()));
            }
            $unknown = array_diff(array_keys($hint), ['type', 'format', 'example']);
            if ($unknown !== []) {
                $this->fail(sprintf(
                    '%s::openApiSchema() returned %s. Return only type, format and example; they describe the wire value and do not change validation.',
                    $class,
                    implode(', ', $unknown),
                ));
            }
            $schema = $hint + ['type' => 'string'];
        }
        // without a hint the wire value of a caster is a string; its result type says nothing about the request
        $schema ??= ['type' => 'string'];

        return ['class' => $class, 'arguments' => $cast->arguments, 'schema' => $schema];
    }

    /**
     * @param \ReflectionClass<*> $caster
     * @param \ReflectionClass<*> $dto
     */
    private function casterReturn(\ReflectionClass $caster, \ReflectionClass $dto, string $property, string $typeName, bool $builtin, ?string $listItem): void
    {
        $method = $caster->getMethod('cast');
        $return = $method->getReturnType();
        if (!$return instanceof \ReflectionNamedType || $return->allowsNull() || $return->getName() === 'mixed') {
            $this->fail(sprintf(
                '%s::cast() must declare a native return type identical to %s::$%s (%s). `mixed` and nullable returns are not accepted.',
                $caster->getName(),
                $dto->getName(),
                $property,
                $listItem !== null ? 'array' : $typeName,
            ));
        }

        $returnName = $return->getName();
        if ($listItem !== null) {
            if ($returnName !== 'array') {
                $this->fail(sprintf(
                    '%s::cast() must return array for %s::$%s, which is list<%s>. It returns %s.',
                    $caster->getName(),
                    $dto->getName(),
                    $property,
                    $listItem,
                    $returnName,
                ));
            }
            $documented = $this->documentedList($method);
            $expected = $listItem;
            if ($documented === null || TypeName::resolve($documented, $caster) !== $expected) {
                $this->fail(sprintf(
                    '%s::cast() must document `@return list<%s>` because %s::$%s is that list%s. The check reads the docblock of cast().',
                    $caster->getName(),
                    $listItem,
                    $dto->getName(),
                    $property,
                    $documented === null ? '' : sprintf(' (it documents list<%s>; a caster gives one item type, for example Split gives list<string>, so list<int> needs a caster of its own)', $documented),
                ));
            }
        }
        if ($builtin) {
            if ($returnName !== $typeName) {
                $this->fail(sprintf(
                    '%s::cast() must return %s, the type of %s::$%s. It returns %s.',
                    $caster->getName(),
                    $typeName,
                    $dto->getName(),
                    $property,
                    $returnName,
                ));
            }

            return;
        }

        if (!is_a($returnName, $typeName, true)) {
            $this->fail(sprintf(
                '%s::cast() must return %s or a subtype, the type of %s::$%s. It returns %s.',
                $caster->getName(),
                $typeName,
                $dto->getName(),
                $property,
                $returnName,
            ));
        }
    }

    private function documentedList(\ReflectionMethod $method): ?string
    {
        $doc = $method->getDocComment();
        if ($doc === false || preg_match('/@return\s+list\s*<\s*([^>]+?)\s*>/', $doc, $match) !== 1) {
            return null;
        }

        return $match[1];
    }

    /**
     * Checks the defaults with every sibling known, so a cross-field constraint can read the other properties.
     * A sibling without a default is null, as for an omitted value at runtime.
     *
     * @param list<Field> $fields
     */
    private function checkDefaults(string $class, array $fields): void
    {
        $properties = array_map(static fn (Field $field): string => $field->property, $fields);
        $defaults = [];
        foreach ($fields as $field) {
            if ($field->hasDefault) {
                $defaults[$field->property] = $field->default;
            }
        }
        foreach ($fields as $field) {
            if (!$field->hasDefault || $field->default === null) {
                continue;
            }
            $context = new ConstraintContext($field->property, $class, $properties, $defaults);
            foreach ($field->constraints as $constraint) {
                $message = $constraint->validate($field->default, $context);
                if ($message !== null) {
                    $this->fail(sprintf(
                        '%s::$%s default does not satisfy #[%s]: %s. Change the default or the constraint.',
                        $class,
                        $field->property,
                        new \ReflectionClass($constraint)->getShortName(),
                        $message,
                    ));
                }
            }
        }
    }

    /**
     * The default is kept in the compiled container, so it is null, a scalar, a list or map of those, or a backed enum case.
     * Rejecting objects here makes the container and the plain runtime behave the same.
     */
    private function checkDefaultValue(string $class, string $property, mixed $default): void
    {
        if (is_array($default)) {
            foreach ($default as $item) {
                $this->checkDefaultValue($class, $property, $item);
            }
        } elseif (is_object($default) && !$default instanceof \BackedEnum) {
            $this->fail(sprintf(
                '%s::$%s has a default of type %s. A default is null, a scalar, an array or a backed enum case, because it is stored in the compiled container. Use `= null` and create the %s in the handler.',
                $class,
                $property,
                $default::class,
                new \ReflectionClass($default)->getShortName(),
            ));
        }
    }

    /**
     * A list default is a PHP value of the result type, so every item has to have the declared item type.
     */
    private function checkListDefault(string $class, string $property, mixed $default, ?string $listItem, bool $listItemBuiltin): void
    {
        if (!is_array($default) || $listItem === null) {
            return;
        }
        if (!array_is_list($default)) {
            $this->fail(sprintf('%s::$%s default must be a list, for example [], because the property is list<%s>.', $class, $property, $listItem));
        }
        foreach ($default as $index => $item) {
            $valid = match (true) {
                !$listItemBuiltin => $item instanceof $listItem,
                $listItem === 'int' => is_int($item),
                $listItem === 'float' => is_float($item) || is_int($item),
                $listItem === 'bool' => is_bool($item),
                default => is_string($item),
            };
            if (!$valid) {
                $this->fail(sprintf(
                    '%s::$%s default item %d is %s, but the property is list<%s>. Change the default.',
                    $class,
                    $property,
                    $index,
                    get_debug_type($item),
                    $listItem,
                ));
            }
        }
    }

    /**
     * @param list<Field> $fields
     */
    private static function containsFile(array $fields): bool
    {
        return array_any($fields, static fn (Field $field): bool => $field->source === Source::File);
    }

    /** @param \ReflectionClass<*> $class */
    private function touch(\ReflectionClass $class): void
    {
        // the class and its parents: an inherited constructor or default changes the metadata too
        for ($current = $class; $current instanceof \ReflectionClass; $current = $current->getParentClass() ?: null) {
            $this->touchFile($current);
            $this->touchTraits($current);
        }
    }

    /** @param \ReflectionClass<*> $class */
    private function touchTraits(\ReflectionClass $class): void
    {
        // a trait can declare the properties, the constructor or the constants, and it may use traits itself
        foreach ($class->getTraits() as $trait) {
            $this->touchFile($trait);
            $this->touchTraits($trait);
        }
    }

    /** @param \ReflectionClass<*> $class */
    private function touchFile(\ReflectionClass $class): void
    {
        $file = $class->getFileName();
        if (is_string($file)) {
            $this->files[$file] = $file;
        }
    }

    private function fail(string $message): never
    {
        throw new \LogicException($message);
    }
}
