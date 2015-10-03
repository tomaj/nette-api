<?php

namespace Tomaj\NetteApi\Params;

use Exception;

class InputParam
{
    const TYPE_POST = 'POST';
    const TYPE_GET  = 'GET';
    const TYPE_FILE = 'FILE';

    const OPTIONAL = false;
    const REQUIRED = true;

    private $type;

    private $key;

    private $required;

    private $availableValues;

    public function __construct($type, $key, $required = self::OPTIONAL, $availableValues = null)
    {
        $this->type = $type;
        $this->key = $key;
        $this->required = $required;
        $this->availableValues = $availableValues;
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

    public function isValid()
    {
        $value = $this->getValue();
        if ($this->availableValues != null) {
            if (is_array($this->availableValues)) {
                return in_array($value, $this->availableValues);
            }
        }

        if ($this->required) {
            if ($value == null || $value = '') {
                return false;
            }
            if (is_string($this->availableValues)) {
                return $value == $this->availableValues;
            }
        }
        return true;
    }

    public function getValue()
    {
        if ($this->type == self::TYPE_GET) {
            if (!filter_has_var(INPUT_GET, $this->key) && isset($_GET[$this->key])) {
                return $_GET[$this->key];
            }
            return filter_input(INPUT_GET, $this->key);
        }
        if ($this->type == self::TYPE_POST) {
            if (!filter_has_var(INPUT_POST, $this->key) && isset($_POST[$this->key])) {
                return $_POST[$this->key];
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
