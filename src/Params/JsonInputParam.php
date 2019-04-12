<?php

namespace Tomaj\NetteApi\Params;

use Exception;
use JsonSchema\Validator;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Nette\Utils\Html;

class JsonInputParam extends InputParam
{
    protected $type = self::TYPE_POST_JSON;

    private $schemaValidator;

    private $schema;

    public function __construct(string $key, string $schema)
    {
        parent::__construct($key);
        $this->schemaValidator = new Validator();
        $this->schema = $schema;
    }

    public function setMulti(): InputParam
    {
        throw new Exception('Cannot use multi json input param');
    }

    public function getValue()
    {
        $input = file_get_contents("php://input");
        return json_decode($input, true);
    }

    public function isValid(): bool
    {
        $value = $this->getValue();
        if (json_last_error()) {
            $this->errors[] = json_last_error_msg();
            return false;
        }

        if (!$value && $this->isRequired() === self::OPTIONAL) {
            return true;
        }

        $value = json_decode(json_encode($value));
        $this->schemaValidator->validate($value, json_decode($this->schema));

        foreach ($this->schemaValidator->getErrors() as $error) {
            $this->errors[] = $error['message'];
        }

        return $this->schemaValidator->isValid();
    }

    protected function addFormInput(Form $form, string $key): BaseControl
    {
        return $form->addTextArea('post_raw', $this->getParamLabel())
            ->setHtmlAttribute('rows', 10)
            ->setOption('description', Html::el()->setHtml(
                '<div id="show_schema_link"><a href="#" onclick="document.getElementById(\'json_schema\').style.display = \'block\'; document.getElementById(\'show_schema_link\').style.display = \'none\'; return false;">Show schema</a></div>
                            <div id="json_schema" style="display: none;">
                            <div><a href="#" onclick="document.getElementById(\'show_schema_link\').style.display = \'block\'; document.getElementById(\'json_schema\').style.display = \'none\'; return false;">Hide schema</a></div>'
                . nl2br(str_replace(' ', '&nbsp;', json_encode(json_decode($this->schema), JSON_PRETTY_PRINT)))
                . '</div>'
            ));
    }
}
