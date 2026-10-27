<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use Nette\Application\UI\Form;
use Nette\Forms\Controls\BaseControl;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Params\FileInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\ParamInterface;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Params\RawInputParam;

final class ConsoleParamsTest extends TestCase
{
    public function testUpdateFormFileAndGetInput(): void
    {
        $inputParams = [
            new GetInputParam('asdsd'),
            new FileInputParam('file')
        ];
        $this->addInputsToForm($inputParams);
    }

    public function testUpdateFormPostInputs(): void
    {
        $inputParams = [
            new PostInputParam('mykey1')->setRequired()->addExample('default', 'Hello world'),
            new PostInputParam('mykey2')->setMulti(),
            new PostInputParam('mykey3')->setRequired()->setAvailableValues(['a', 'b']),
        ];
        $this->addInputsToForm($inputParams);
    }

    public function testUpdateFormJsonInput(): void
    {
        $inputParams = [
            new JsonInputParam('json', '{}'),
        ];
        $this->addInputsToForm($inputParams);
    }

    public function testUpdateFormRawInput(): void
    {
        $inputParams = [
            new RawInputParam('raw'),
        ];
        $this->addInputsToForm($inputParams);
    }

    /**
     * @param ParamInterface[] $inputParams
     */
    private function addInputsToForm(array $inputParams): void
    {
        $form = new Form();
        $totalCount = 0;
        foreach ($inputParams as $inputParam) {
            $totalCount += $inputParam->isMulti() ? 5 : 1;
            $totalCount++;  // each input param has one "do_not_send_empty_value" checkbox added
            $inputParam->updateConsoleForm($form);
        }

        $this->assertCount($totalCount, iterator_to_array($form->getControls()));

        $this->assertContainsOnlyInstancesOf(BaseControl::class, iterator_to_array($form->getControls()));
    }
}
