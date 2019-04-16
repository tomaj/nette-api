<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Exception;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;

class RawInputParam extends InputParam
{
    protected $type = self::TYPE_POST_RAW;

    public function setMulti(): InputParam
    {
        throw new Exception('Cannot use multi raw input param');
    }

    public function getValue()
    {
        return file_get_contents("php://input") ?: $this->default;
    }

    protected function addFormInput(Form $form, string $key): BaseControl
    {
        return $form->addTextArea('post_raw', $this->getParamLabel())
            ->setHtmlAttribute('rows', 10);
    }
}
