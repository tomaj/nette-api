<?php

namespace Tomaj\NetteApi\Params;

use Exception;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Nette\Utils\Html;
use Tomaj\NetteApi\Validation\JsonSchemaValidator;
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

    /** @var mixed */
    protected $example;

    /** @var array */
    protected $schema;

    protected $internalType = 'string';

    public function __construct(string $key, string $schema = '{"type": "string"}')
    {
        $this->key = $key;
        $this->schema = json_decode($schema, true);

        $internalType = $this->schema['type'] ?? 'string';
        $this->schema['type'] = $internalType;
        if ($internalType === 'array') {
            $internalType = $this->schema['items']['type'] ?? 'string';
        }
        $this->internalType = $internalType;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setRequired(): self
    {
        $this->required = self::REQUIRED;
        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setAvailableValues(array $availableValues): self
    {
        $this->availableValues = $availableValues;
        $this->schema['enum'] = $availableValues;
        return $this;
    }

    public function getAvailableValues(): ?array
    {
        return $this->availableValues;
    }

    public function setMulti(): self
    {
        $this->multi = true;
        $this->schema['type'] = 'array';
        return $this;
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
     * @param mixed $example
     * @return self
     */
    public function setExample($example): self
    {
        $this->example = $example;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getExample()
    {
        return $this->example;
    }

    /**
     * @return mixed
     * @throws Exception if cast failed
     */
    final public function value()
    {
        return $this->castValue($this->getValue());
    }

    /**
     * @return mixed
     */
    abstract protected function getValue();

    public function getSchema(): string
    {
        if (!$this->isRequired()) {
            $this->schema['type'] = is_array($this->schema['type']) ? $this->schema['type'] + ['null'] : [$this->schema['type'], 'null'];
        }
        return json_encode($this->schema);
    }

    public function updateConsoleForm(Form $form): void
    {
        $count = $this->isMulti() ? 5 : 1;
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
    }

    protected function addFormInput(Form $form, string $key): BaseControl
    {
        if ($this->getAvailableValues()) {
            return $form->addSelect($key, $this->getParamLabel(), array_combine($this->getAvailableValues(), $this->getAvailableValues()))
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
        try {
            $value = $this->value();
        } catch (Exception $e) {
            return new ValidationResult(ValidationResult::STATUS_ERROR, [$e->getMessage()]);
        }

        $schemaValidator = new JsonSchemaValidator();
        return $schemaValidator->validate($value, $this->getSchema());
    }

    /**
     * @param mixed $value
     * @return mixed
     * @throws Exception if cast failed
     */
    protected function castValue($value)
    {
        if ($value === '' || $value === null) {
            return null;
        }
        if (is_array($value)) {
            $newValue = array_filter(array_map(function ($item) {
                return $this->castValue($item);
            }, $value), function ($item) {
                return $item !== null;
            });
        } elseif ($this->internalType === 'boolean') {
            $newValue = (bool)$value;
            $this->checkCast($value, $newValue);
        } elseif ($this->internalType === 'integer') {
            $newValue = (int)$value;
            $this->checkCast($value, $newValue);
        } else {
            $newValue = $value;
        }

        return $newValue;
    }

    /**
     * checks if casted value is the same as original value
     *
     * @param mixed $originalValue
     * @param mixed $newValue
     * @throws Exception if new value doesn't match original value
     */
    private function checkCast($originalValue, $newValue)
    {
        if ($this->internalType === 'boolean') {
            $originalValue = (int)$originalValue;
            $newValue = (int)$newValue;
        }

        if ((string)$originalValue !== (string)$newValue) {
            throw new Exception('Cast of ' . $originalValue . ' to ' . $this->internalType . ' failed');
        }
    }
}
