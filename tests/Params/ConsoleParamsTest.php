<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use Exception;
use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Params\CookieInputParam;
use Tomaj\NetteApi\Params\FileInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\ParamInterface;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Params\PutInputParam;
use Tomaj\NetteApi\Params\RawInputParam;

class ConsoleParamsTest extends TestCase
{
    public function testUpdateFormFileAndGetInput()
    {
        $inputParams = [
            new GetInputParam('asdsd'),
            new FileInputParam('file')
        ];
        $this->addInputsToForm($inputParams);
    }

    public function testUpdateFormPostInputs()
    {
        $inputParams = [
            (new PostInputParam('mykey1'))->setRequired()->setExample('Hello world'),
            (new PostInputParam('mykey2'))->setMulti(),
            (new PostInputParam('mykey3'))->setRequired()->setAvailableValues(['a', 'b']),
        ];
        $this->addInputsToForm($inputParams);
    }

    public function testUpdateFormJsonInput()
    {
        $inputParams = [
            new JsonInputParam('json', '{}'),
        ];
        $this->addInputsToForm($inputParams);
    }

    public function testUpdateFormRawInput()
    {
        $inputParams = [
            new RawInputParam('raw'),
        ];
        $this->addInputsToForm($inputParams);
    }

    /**
     * @param ParamInterface[] $inputParams
     */
    private function addInputsToForm(array $inputParams)
    {
        $form = new Form();
        $totalCount = 0;
        foreach ($inputParams as $inputParam) {
            $totalCount += $inputParam->isMulti() ? 5 : 1;
            $totalCount++;  // each input param has one "do_not_send_empty_value" checkbox added
            $inputParam->updateConsoleForm($form);
        }

        $this->assertCount($totalCount, $form->getControls());

        foreach ($form->getControls() as $control) {
            $this->assertInstanceOf(BaseControl::class, $control);
        }
    }
}
