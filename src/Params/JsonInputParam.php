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

    private $rawInput;

    /** @var array */
    protected $additionalExamples = [];

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
        $input = $this->rawInput = file_get_contents("php://input") ?: $this->default;
        if ($input === null) {
            $input = '';
        }
        return json_decode($input, true);
    }

    public function validate(): ValidationResultInterface
    {
        $value = $this->getValue();
        if (empty($this->rawInput) && $this->isRequired() === self::REQUIRED) {
            return new ValidationResult(ValidationResult::STATUS_ERROR, ['missing data']);
        }

        if (!empty($this->rawInput) && json_last_error()) {
            return new ValidationResult(ValidationResult::STATUS_ERROR, [json_last_error_msg()]);
        }

        if (!$value && $this->isRequired() === self::OPTIONAL) {
            return new ValidationResult(ValidationResult::STATUS_OK);
        }

        $value = json_decode(json_encode($value));
        $schemaValidator = new JsonSchemaValidator();
        return $schemaValidator->validate($value, $this->schema);
    }

    public function getSchema(): string
    {
        return $this->schema;
    }

    /**
     * Set multiple examples for request. This is useful for testing. 
     * Associative names will be used as example name. 
     * [
     *  "A" => [ "param1" => "value1", "param2" => "value2" ],
     *  "B" => [ "param1" => "value3", "param2" => "value4" ]
     * ]
     * @param array $examples
     * @return self
     */
    public function setAdditionalExamples(array $examples): self
    {
        if (empty($this->example)) {
            throw new \Exception('You have to set example before you can set additional examples');
        }
        foreach ($examples as &$example) {
            if (!is_array($example)) {
                $example = json_decode($example, true);
            }
        }
        $this->additionalExamples = $examples;
        return $this;
    }

    /**
     * Get additional examples
     * @return array
     */
    public function getAdditionalExamples(): array  
    {
        return $this->additionalExamples;
    }

    protected function addFormInput(Form $form, string $key): BaseControl
    {
        $fullSchema = json_decode($this->schema, true);

        if (!empty($this->getAdditionalExamples())) {
            $fullSchema['examples'] = array_merge(
                ["default" => is_array($this->getExample())? $this->getExample() : json_decode($this->getExample(), true)],
                $this->getAdditionalExamples(),
            );
        } else {
            $fullSchema['example'] = is_array($this->getExample())? $this->getExample() : json_decode($this->getExample(), true);
        }


        if (!empty($fullSchema['examples'])) {
            $this->description .= <<< HTML
                <div>
                    Select Example:&nbsp; 
HTML;              
            foreach ($fullSchema['examples'] as $exampleKey => $exampleValue) {
                $example = htmlentities(json_encode($exampleValue, JSON_PRETTY_PRINT));
                $this->description .= <<< HTML
                <div class="btn btn-sm" data-example="{$example}" onClick="setExample(this)" >
                    {$exampleKey}
                </div>
HTML;                
            }
            $this->description .= <<< HTML
                <script>
                    function setExample(btn) {
                        var input = document.getElementsByName('post_raw')[0];
                        input.value = btn.dataset.example;
                    }
                </script>
                </div>

HTML;
        }
        $this->description .= '<div id="show_schema_link"><a href="#" onclick="document.getElementById(\'json_schema\').style.display = \'block\'; document.getElementById(\'show_schema_link\').style.display = \'none\'; return false;">Show schema</a></div>
                            <div id="json_schema" style="display: none;">
                            <div><a href="#" onclick="document.getElementById(\'show_schema_link\').style.display = \'block\'; document.getElementById(\'json_schema\').style.display = \'none\'; return false;">Hide schema</a></div>'
            . nl2br(str_replace(' ', '&nbsp;', json_encode($fullSchema, JSON_PRETTY_PRINT))) . '</div>';

        return $form->addTextArea('post_raw', $this->getParamLabel())
            ->setHtmlAttribute('rows', 10);
    }
}
