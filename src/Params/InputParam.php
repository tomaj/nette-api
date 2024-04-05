<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Nette\Utils\Html;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

abstract class InputParam implements ParamInterface
{
    const TYPE_POST      = 'POST';
    const TYPE_GET       = 'GET';
    const TYPE_PUT       = 'PUT';
    const TYPE_FILE      = 'FILE';
    const TYPE_COOKIE    = 'COOKIE';
    const TYPE_POST_RAW  = 'POST_RAW';
    const TYPE_POST_JSON = 'POST_JSON';

    const OPTIONAL = false;
    const REQUIRED = true;

    /** @var string */
    protected $type;

    /** @var string */
    protected $key;

    /** @var bool */
    protected $required = self::OPTIONAL;

    /** @var array|null */
    protected $availableValues = null;

    /** @var bool */
    protected $multi = false;

    /** @var string */
    protected $description = '';

    /** @var mixed */
    protected $default;

    /** @var array */
    protected $examples = [];

    public function __construct(string $key)
    {
        $this->key = $key;
    }

    public function setRequired(): self
    {
        $this->required = self::REQUIRED;
        return $this;
    }

    public function setAvailableValues(array $availableValues): self
    {
        if ($availableValues === array_values($availableValues)) {
            $availableValues = array_combine($availableValues, $availableValues);
        }
        $this->availableValues = $availableValues;
        return $this;
    }

    public function setMulti(): self
    {
        $this->multi = true;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function getAvailableValues(): ?array
    {
        return $this->availableValues;
    }

    public function isMulti(): bool
    {
        return $this->multi;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param mixed $default
     * @return self
     */
    public function setDefault($default): self
    {
        $this->default = $default;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getDefault()
    {
        return $this->default;
    }

    /**
     * Add example, can be used multiple times to add many examples
     * @param string $name Example name
     * @param mixed $example Example
     * @return Self
     */
    public function addExample(string $name, $example): self
    {
        $this->examples[$name] = $example;
        return $this;
    }

    /**
     * Set default example
     * @param mixed $example
     * @return self
     * @deprecated Use addExample instead
     */
    public function setExample($example): self
    {
        $this->examples["default"] = $example;
        return $this;
    }

    /**
     * Returns first example
     * @return mixed
     */
    public function getExample()
    {
        if (empty($this->examples)) {
            return null;
        }
        return reset($this->examples);
    }

    /**
     * Returns all examples
     * @return array
     */
    public function getExamples(): array
    {
        return $this->examples;
    }

    public function updateConsoleForm(Form $form): void
    {
        $count = $this->isMulti() ? 5 : 1;  // TODO moznost nastavit kolko inputov sa ma vygenerovat v konzole, default moze byt 5
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
        $title .= ' (' . $this->getType() . ')';
        return $title;
    }

    /**
     * Check if actual value from environment is valid
     */
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
            $result = empty(array_diff(($this->isMulti() ? $value : [$value]), array_keys($this->getAvailableValues())));
            if ($result === false) {
                return new ValidationResult(ValidationResult::STATUS_ERROR, ['Field contains not available value(s)']);
            }
        }

        return new ValidationResult(ValidationResult::STATUS_OK);
    }
}
