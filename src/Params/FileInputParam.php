<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;

class FileInputParam extends InputParam
{
    protected $type = self::TYPE_FILE;

    protected function addFormInput(Form $form, string $key): BaseControl
    {
        return $form->addUpload($key, $this->getParamLabel());
    }

    public function getValue()
    {
        if (isset($_FILES[$this->key])) {
            return $this->isMulti() ? $this->processMultiFileUploads($_FILES[$this->key]) : $_FILES[$this->key];
        }
        return $this->default;
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
