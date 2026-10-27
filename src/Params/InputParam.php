<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Nette\Utils\Html;
use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;
use Tomaj\NetteApi\Validation\InputType;
use Tomaj\NetteApi\Validation\InputValidator;

abstract class InputParam implements ParamInterface
{
    public const TYPE_POST      = 'POST';
    public const TYPE_GET       = 'GET';
    public const TYPE_PUT       = 'PUT';
    public const TYPE_FILE      = 'FILE';
    public const TYPE_COOKIE    = 'COOKIE';
    public const TYPE_POST_RAW  = 'POST_RAW';
    public const TYPE_POST_JSON = 'POST_JSON';

    public const OPTIONAL = false;
    public const REQUIRED = true;
    public const DEFAULT_MULTI_INPUT_COUNT = 5;

    /** @var string */
    protected $type;

    /** @var string */
    protected $key;

    /** @var bool */
    protected $required = self::OPTIONAL;

    /** @var array<array-key, mixed>|null */
    protected $availableValues = null;

    /** @var bool */
    protected $multi = false;

    /** @var string */
    protected $description = '';

    /** @var mixed */
    protected $default;

    /** @var array<string, mixed> */
    protected $examples = [];

    /** @var string|null */
    protected $valueType;

    /**
     * @param InputType|string|null $valueType expected value type, see InputType
     */
    public function __construct(string $key, InputType|string|null $valueType = null)
    {
        $this->key = $key;
        $this->valueType = $valueType instanceof InputType ? $valueType->value : $valueType;
    }

    /**
     * Returns where the param is read from as an enum (see getType() for the string value),
     * null for a custom type that is not one of the TYPE_* constants
     */
    public function getParamType(): ?ParamType
    {
        return ParamType::tryFrom($this->getType());
    }

    public function setRequired(): self
    {
        $this->required = self::REQUIRED;
        return $this;
    }

    /**
     * @param array<array-key, mixed> $availableValues
     */
    public function setAvailableValues(array $availableValues): self
    {
        if ($availableValues === array_values($availableValues)) {
            $keyed = [];
            foreach ($availableValues as $availableValue) {
                $keyed[TypeHelper::toString($availableValue)] = $availableValue;
            }

            $availableValues = $keyed;
        }

        $this->availableValues = $availableValues;
        return $this;
    }

    public function setMulti(): self
    {
        $this->multi = true;
        return $this;
    }

    #[\Override]
    public function getType(): string
    {
        return $this->type;
    }

    #[\Override]
    public function getKey(): string
    {
        return $this->key;
    }

    #[\Override]
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    #[\Override]
    public function getAvailableValues(): ?array
    {
        return $this->availableValues;
    }

    #[\Override]
    public function isMulti(): bool
    {
        return $this->multi;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    #[\Override]
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param mixed $default
     */
    public function setDefault($default): self
    {
        $this->default = $default;
        return $this;
    }

    #[\Override]
    public function getDefault(): mixed
    {
        return $this->default;
    }

    /**
     * Add example, can be used multiple times to add many examples
     * @param string $name Example name
     * @param mixed $example Example
     */
    public function addExample(string $name, $example): self
    {
        $this->examples[$name] = $example;
        return $this;
    }

    #[\Override]
    public function getExample(): mixed
    {
        if (empty($this->examples)) {
            return null;
        }

        return reset($this->examples);
    }

    /**
     * Returns all examples
     * @return array<string, mixed>
     */
    public function getExamples(): array
    {
        return $this->examples;
    }

    #[\Override]
    public function updateConsoleForm(Form $form): void
    {
        $count = $this->isMulti() ? self::DEFAULT_MULTI_INPUT_COUNT : 1; // TODO moznost nastavit kolko inputov sa ma vygenerovat v konzole, default moze byt 5
        for ($i = 0; $i < $count; $i++) {
            $key = $this->getKey();
            if ($this->isMulti()) {
                $key = $key . '___' . $i;
            }

            $input = $this->addFormInput($form, $key);
            if ($this->description) {
                $input->setOption('description', Html::el('div', ['class' => 'param-description'])->setHtml($this->description));
            }

            if ($this->getExample() || $this->getDefault()) {
                $default = $this->getExample() ?: $this->getDefault();
                $default = is_array($default) ? ($default[$i] ?? null) : $default;
                $input->setDefaultValue($default);
            }
        }

        $form->addCheckbox('do_not_send_empty_value_for_' . $this->getKey(), 'Do not send empty value for ' . $this->getLabel());
    }

    protected function addFormInput(Form $form, string $key): BaseControl
    {
        if ($this->getAvailableValues()) {
            return $form->addSelect($key, $this->getParamLabel(), $this->getAvailableValues())
                ->setPrompt('Select ' . $this->getLabel());
        }

        return $form->addText($key, $this->getParamLabel());
    }

    protected function getLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->getKey()));
    }

    protected function getParamLabel(): string
    {
        $title = $this->getLabel();
        if ($this->isRequired()) {
            $title .= ' *';
        }
        return $title . (' (' . $this->getType() . ')');
    }

    /**
     * Check if actual value from environment is valid
     */
    #[\Override]
    public function validate(): ValidationResultInterface
    {
        $value = $this->getValue();
        if ($this->required === self::OPTIONAL && ($value === null || $value === '')) {
            return new ValidationResult(ValidationResult::STATUS_OK);
        }

        if ($this->required && ($value === null || $value === '')) {
            return new ValidationResult(ValidationResult::STATUS_ERROR, ['Field is required']);
        }

        if ($this->getAvailableValues() !== null) {
            $result = empty(array_diff(TypeHelper::toStringList($this->isMulti() && is_array($value) ? $value : [$value]), TypeHelper::toStringList(array_keys($this->getAvailableValues()))));
            if ($result === false) {
                return new ValidationResult(ValidationResult::STATUS_ERROR, ['Field contains not available value(s)']);
            }
        }

        $inputValidator = new InputValidator();
        return $inputValidator->validate($value, $this->valueType);
    }
}
