<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Exception;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Tomaj\NetteApi\Validation\JsonSchemaValidator;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

class JsonInputParam extends InputParam
{
    protected $type = self::TYPE_POST_JSON;

    private $schema;

    public function __construct(string $key, string $schema)
    {
        parent::__construct($key);
        $this->schema = $schema;
    }

    public function setMulti(): InputParam
    {
        throw new Exception('Cannot use multi json input param');
    }

    public function getValue()
    {
        $input = file_get_contents("php://input") ?: $this->default;
        if ($input === null) {
            $input = '';
        }
        return json_decode($input, true);
    }

    public function validate(): ValidationResultInterface
    {
        $value = $this->getValue();
        if (json_last_error()) {
            return new ValidationResult(ValidationResult::STATUS_ERROR, [json_last_error_msg()]);
        }

        if (!$value && $this->isRequired() === self::OPTIONAL) {
            return new ValidationResult(ValidationResult::STATUS_OK);
        }

        $value = json_decode(json_encode($value));
        $schemaValidator = new JsonSchemaValidator();
        return $schemaValidator->validate($value, $this->schema);
    }

    protected function addFormInput(Form $form, string $key): BaseControl
    {
        $this->description .= '<div id="show_schema_link"><a href="#" onclick="document.getElementById(\'json_schema\').style.display = \'block\'; document.getElementById(\'show_schema_link\').style.display = \'none\'; return false;">Show schema</a></div>
                            <div id="json_schema" style="display: none;">
                            <div><a href="#" onclick="document.getElementById(\'show_schema_link\').style.display = \'block\'; document.getElementById(\'json_schema\').style.display = \'none\'; return false;">Hide schema</a></div>'
            . nl2br(str_replace(' ', '&nbsp;', json_encode(json_decode($this->schema), JSON_PRETTY_PRINT))) . '</div>';

        return $form->addTextArea('post_raw', $this->getParamLabel())
            ->setHtmlAttribute('rows', 10);
    }
}
