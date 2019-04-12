<?php

namespace Tomaj\NetteApi\Params;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;

abstract class InputParam implements ParamInterface
{
    const TYPE_POST      = 'POST';
    const TYPE_GET       = 'GET';
    const TYPE_PUT       = 'PUT';
    const TYPE_FILE      = 'FILE';
    const TYPE_COOKIE    = 'COOKIE';
    const TYPE_POST_RAW  = 'POST_RAW';
    const TYPE_POST_JSON  = 'POST_JSON';

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

    protected $errors = [];

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

    public function updateConsoleForm(Form $form): void
    {
        $count = $this->isMulti() ? 5 : 1;  // TODO moznost nastavit kolko inputov sa ma vygenerovat v konzole, default moze byt 5
        for ($i = 0; $i < $count; $i++) {
            $key = $this->getKey();
            if ($this->isMulti()) {
                $key = $key . '___' . $i;
            }
            $this->addFormInput($form, $key);
        }
    }

    protected function addFormInput(Form $form, string $key): BaseControl
    {
        if ($this->getAvailableValues()) {
            $select = $form->addSelect($key, $this->getParamLabel(), array_combine($this->getAvailableValues(), $this->getAvailableValues()))
                ->setPrompt('Select ' . $this->getLabel());
            return $select;
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
    public function isValid(): bool
    {
        $value = $this->getValue();
        if ($this->required === self::OPTIONAL && ($value === null || $value == '')) {
            return true;
        }

        if ($this->required && ($value === null || $value == '')) {
            $this->errors[] = 'Field is required';
            return false;
        }

        if ($this->availableValues !== null) {
            if (is_array($this->availableValues)) {
                $result = empty(array_diff(($this->isMulti() ? $value : [$value]), $this->availableValues));
                if ($result === false) {
                    $this->errors[] = 'Field contains not available value(s)';
                }
                return $result;
            }
        }

        return true;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
