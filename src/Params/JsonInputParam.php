<?php

namespace Tomaj\NetteApi\Params;

use Exception;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;

class JsonInputParam extends InputParam
{
    protected $type = self::TYPE_POST_JSON;

    public function __construct(string $key, string $schema = '{"type": "object"}')
    {
        parent::__construct($key, $schema);
    }

    public function setMulti(): InputParam
    {
        throw new Exception('Cannot use multi json input param');
    }

    public function getValue()
    {
        $input = file_get_contents("php://input") ?: $this->default;
        $value = json_decode($input);
        if (json_last_error()) {
            throw new Exception(json_last_error_msg());
        }
        return $value;
    }

    protected function addFormInput(Form $form, string $key): BaseControl
    {
        $this->description .= '<div id="show_schema_link"><a href="#" onclick="document.getElementById(\'json_schema\').style.display = \'block\'; document.getElementById(\'show_schema_link\').style.display = \'none\'; return false;">Show schema</a></div>
                            <div id="json_schema" style="display: none;">
                            <div><a href="#" onclick="document.getElementById(\'show_schema_link\').style.display = \'block\'; document.getElementById(\'json_schema\').style.display = \'none\'; return false;">Hide schema</a></div>'
            . nl2br(str_replace(' ', '&nbsp;', json_encode(json_decode($this->getSchema()), JSON_PRETTY_PRINT))) . '</div>';

        return $form->addTextArea('post_raw', $this->getParamLabel())
            ->setHtmlAttribute('rows', 10);
    }
}
