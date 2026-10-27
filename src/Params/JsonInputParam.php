<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Exception;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use Tomaj\NetteApi\Misc\TypeHelper;
use Tomaj\NetteApi\ValidationResult\ValidationResult;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;
use Tomaj\NetteApi\Validation\JsonSchemaValidator;

class JsonInputParam extends InputParam
{
    protected $type = self::TYPE_POST_JSON;

    private mixed $rawInput = null;

    public function __construct(string $key, private readonly string $schema)
    {
        parent::__construct($key);
    }

    #[\Override]
    public function setMulti(): InputParam
    {
        throw new Exception('Cannot use multi json input param');
    }

    #[\Override]
    public function getValue(): mixed
    {
        $input = $this->rawInput = file_get_contents('php://input') ?: $this->default;

        return json_decode(TypeHelper::toString($input), true);
    }

    #[\Override]
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

        $value = json_decode(json_encode($value) ?: '');
        $schemaValidator = new JsonSchemaValidator();
        return $schemaValidator->validate($value, $this->schema);
    }

    public function getSchema(): string
    {
        return $this->schema;
    }

    #[\Override]
    protected function addFormInput(Form $form, string $key): BaseControl
    {
        $fullSchema = TypeHelper::toArray(json_decode($this->schema, true));

        if (!empty($examples = $this->getExamples())) {
            if (count($examples) === 1) {
                $example = $this->getExample();
                $fullSchema['example'] = is_array($example) ? $example : json_decode(TypeHelper::toString($example), true);
            } else {
                $schemaExamples = [];
                foreach ($examples as $exampleKey => $example) {
                    $schemaExamples[$exampleKey] = is_array($example) ? $example : json_decode(TypeHelper::toString($example), true);
                }

                $fullSchema['examples'] = $schemaExamples;
            }
        }

        $schemaExamples = $fullSchema['examples'] ?? null;
        if (is_array($schemaExamples) && $schemaExamples !== []) {
            $this->description .= <<< HTML
                <div>
                    Select Example:&nbsp; 
HTML;
            foreach ($schemaExamples as $exampleKey => $exampleValue) {
                $example = htmlentities(json_encode($exampleValue, JSON_PRETTY_PRINT) ?: '');
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
            . nl2br(str_replace(' ', '&nbsp;', json_encode($fullSchema, JSON_PRETTY_PRINT) ?: '')) . '</div>';

        return $form->addTextArea('post_raw', $this->getParamLabel())
            ->setHtmlAttribute('rows', 10);
    }
}
