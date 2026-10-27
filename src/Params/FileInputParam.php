<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;

class FileInputParam extends InputParam
{
    protected $type = self::TYPE_FILE;

    #[\Override]
    protected function addFormInput(Form $form, string $key): BaseControl
    {
        return $form->addUpload($key, $this->getParamLabel());
    }

    #[\Override]
    public function getValue(): mixed
    {
        if (isset($_FILES[$this->key])) {
            return $this->isMulti() ? $this->processMultiFileUploads($_FILES[$this->key]) : $_FILES[$this->key];
        }

        return $this->default;
    }

    /**
     * @return array<array-key, array<array-key, mixed>>
     */
    private function processMultiFileUploads(mixed $files): array
    {
        $result = [];
        foreach (is_array($files) ? $files : [] as $key => $values) {
            foreach (is_array($values) ? $values : [] as $index => $value) {
                $result[$index][$key] = $value;
            }
        }

        return $result;
    }
}
