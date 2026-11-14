<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Tomaj\NetteApi\Input\DtoMapper;
use Tomaj\NetteApi\Input\DtoMetadata;
use Tomaj\NetteApi\Input\Field;
use Tomaj\NetteApi\Input\SchemaBuilder;
use Tomaj\NetteApi\Input\Source;
use Tomaj\NetteApi\ValidationResult\FieldErrors;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

/**
 * One root DTO property as an InputParam. Validation and the value come from the shared DTO mapper.
 */
final class MappedInputParam extends InputParam
{
    public function __construct(
        private readonly Field $field,
        private readonly DtoMapper $mapper,
        DtoMetadata $metadata,
    ) {
        parent::__construct($field->externalName);
        $this->type = match ($field->source) {
            Source::Query => self::TYPE_GET,
            Source::Path => self::TYPE_PATH,
            Source::Header => self::TYPE_HEADER,
            Source::Body => self::TYPE_BODY,
            Source::File => self::TYPE_FILE,
        };
        if ($field->required()) {
            $this->setRequired();
        }
        if ($field->description !== null && $field->description !== '') {
            $this->setDescription($field->description);
        }
        foreach ($field->examples as $name => $example) {
            $this->addExample($name, $example);
        }
        if (SchemaBuilder::documentsDefault($field)) {
            $this->setDefault(SchemaBuilder::wire($field->default));
        }
        if ($field->enumValues !== []) {
            $this->setAvailableValues($field->enumValues);
        }
        // a caster reads the whole wire value (for example Split reads "a,b"), so it is one input, not a list of inputs
        if ($field->listItem !== null && $field->casterClass === null) {
            $this->setMulti();
        }
        $this->setOpenApiSchema(SchemaBuilder::build($field, $field->readsBody()));
        if ($field->readsBody()) {
            $this->setContentTypes($metadata->contentTypes);
        }
    }

    #[\Override]
    public function validate(): ValidationResultInterface
    {
        $errors = $this->mapper->errors($this->field);
        if ($errors === []) {
            return new ValidationResult(ValidationResult::STATUS_OK);
        }

        return new FieldErrors($errors);
    }

    #[\Override]
    public function getValue(): mixed
    {
        return $this->mapper->value($this->field);
    }

    #[\Override]
    protected function addFormInput(Form $form, string $key): BaseControl
    {
        if ($this->getType() === self::TYPE_FILE) {
            return $form->addUpload($key, $this->getParamLabel());
        }

        return parent::addFormInput($form, $key);
    }
}
