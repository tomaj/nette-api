<?php

namespace Tomaj\NetteApi\Params;

use Exception;

class InputParam implements ParamInterface
{
    const TYPE_POST = 'POST';
    const TYPE_GET  = 'GET';
    const TYPE_FILE = 'FILE';

    const OPTIONAL = false;
    const REQUIRED = true;

    /**
     * @var string
     */
    private $type;

    /**
     * @var string
     */
    private $key;

    /**
     * @var bool
     */
    private $required;

    /**
     * @var array|null
     */
    private $availableValues;

    /**
     * @var bool
     */
    private $multi;

    public function __construct($type, $key, $required = self::OPTIONAL, $availableValues = null, $multi = false)
    {
        $this->type = $type;
        $this->key = $key;
        $this->required = $required;
        $this->availableValues = $availableValues;
        $this->multi = $multi;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return boolean
     */
    public function isRequired()
    {
        return $this->required;
    }

    public function getAvailableValues()
    {
        return $this->availableValues;
    }

    /**
     * @return bool
     */
    public function isMulti()
    {
        return $this->multi;
    }

    /**
     * Check if actual value from environment is valid
     *
     * @return bool
     *
     * @throws Exception if actual InputParam has unsupported type
     */
    public function isValid()
    {
        $value = $this->getValue();
        if ($this->availableValues !== null) {
            if (is_array($this->availableValues)) {
                return in_array($value, $this->availableValues);
            }
        }

        if ($this->required) {
            if ($value === null || $value == '') {
                return false;
            }
            if (is_string($this->availableValues)) {
                return $value == $this->availableValues;
            }
        }
        return true;
    }

    /**
     * Process environment variables like POST|GET|etc.. and return actual value
     *
     * @return mixed
     *
     * @throws Exception if actual InputParam has unsupported type
     */
    public function getValue()
    {
        if ($this->type == self::TYPE_GET) {
            if (!filter_has_var(INPUT_GET, $this->key) && isset($_GET[$this->key])) {
                return $_GET[$this->key];
            }
            if ($this->isMulti()) {
                return filter_input(INPUT_GET, $this->key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            }
            return filter_input(INPUT_GET, $this->key);
        }
        if ($this->type == self::TYPE_POST) {
            if (!filter_has_var(INPUT_POST, $this->key) && isset($_POST[$this->key])) {
                return $_POST[$this->key];
            }
            if ($this->isMulti()) {
                return filter_input(INPUT_POST, $this->key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY);
            }
            return filter_input(INPUT_POST, $this->key);
        }
        if ($this->type == self::TYPE_FILE) {
            if (isset($_FILES[$this->key])) {
                return $_FILES[$this->key];
            }
        }

        throw new Exception("Invalid type");
    }
}
