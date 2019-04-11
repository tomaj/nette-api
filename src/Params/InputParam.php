<?php

namespace Tomaj\NetteApi\Params;

use Exception;

class InputParam implements ParamInterface
{
    const TYPE_POST      = 'POST';
    const TYPE_GET       = 'GET';
    const TYPE_PUT       = 'PUT';
    const TYPE_FILE      = 'FILE';
    const TYPE_COOKIE    = 'COOKIE';
    const TYPE_POST_RAW  = 'POST_RAW';
    const TYPE_POST_JSON_KEY  = 'POST_JSON_KEY';

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

    public function __construct(string $type, string $key, bool $required = self::OPTIONAL, ?array $availableValues = null, bool $multi = false)
    {
        $this->type = $type;
        $this->key = $key;
        $this->required = $required;
        $this->availableValues = $availableValues;
        $this->multi = $multi;
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

    /**
     * Check if actual value from environment is valid
     *
     * @return bool
     *
     * @throws Exception if actual InputParam has unsupported type
     */
    public function isValid(): bool
    {
        if ($this->type === self::TYPE_POST_JSON_KEY) {
            $input = file_get_contents("php://input");
            $params = json_decode($input, true);
            if ($input && $params === null) {
                return false;
            }
        }

        if ($this->required === self::OPTIONAL) {
            return true;
        }

        $value = $this->getValue();
        if ($this->availableValues !== null) {
            if (is_array($this->availableValues)) {
                return empty(array_diff(($this->isMulti() ? $value : [$value]), $this->availableValues));
            }
        }

        if ($this->required) {
            if ($value === null || $value == '') {
                return false;
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
                if ($this->isMulti()) {
                    return $this->processMultiFileUploads($_FILES[$this->key]);
                } else {
                    return $_FILES[$this->key];
                }
            }
            return null;
        }
        if ($this->type == self::TYPE_COOKIE) {
            if (isset($_COOKIE[$this->key])) {
                return $_COOKIE[$this->key];
            }
        }
        if ($this->type == self::TYPE_POST_RAW) {
            return file_get_contents("php://input");
        }
        if ($this->type == self::TYPE_PUT) {
            parse_str(file_get_contents("php://input"), $params);
            if (isset($params[$this->key])) {
                return $params[$this->key];
            }
            return '';
        }
        if ($this->type == self::TYPE_POST_JSON_KEY) {
            $params = file_get_contents("php://input");
            $params = json_decode($params, true);
            if (isset($params[$this->key])) {
                return $params[$this->key];
            }
            return '';
        }
        throw new Exception('Invalid type');
    }

    private function processMultiFileUploads($files)
    {
        $result = [];
        foreach ($files as $key => $values) {
            foreach ($values as $index => $value) {
                $result[$index][$key] = $value;
            }
        }
        return $result;
    }
}
