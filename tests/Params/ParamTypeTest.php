<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use Nette\Application\UI\Form;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Params\CookieInputParam;
use Tomaj\NetteApi\Params\FileInputParam;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Params\InputParam;
use Tomaj\NetteApi\Params\JsonInputParam;
use Tomaj\NetteApi\Params\ParamType;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Params\PutInputParam;
use Tomaj\NetteApi\Params\RawInputParam;
use Tomaj\NetteApi\Validation\InputType;
use Tomaj\NetteApi\Validation\InputValidator;

final class ParamTypeTest extends TestCase
{
    public function testParamTypes(): void
    {
        $this->assertSame(ParamType::Get, new GetInputParam('a')->getParamType());
        $this->assertSame(ParamType::Post, new PostInputParam('a')->getParamType());
        $this->assertSame(ParamType::Put, new PutInputParam('a')->getParamType());
        $this->assertSame(ParamType::File, new FileInputParam('a')->getParamType());
        $this->assertSame(ParamType::Cookie, new CookieInputParam('a')->getParamType());
        $this->assertSame(ParamType::PostRaw, new RawInputParam('a')->getParamType());
        $this->assertSame(ParamType::PostJson, new JsonInputParam('a', '{}')->getParamType());
        $this->assertSame(InputParam::TYPE_POST_JSON, ParamType::PostJson->value);
    }

    public function testCustomTypeGivesNull(): void
    {
        $param = new class ('a') extends InputParam {
            protected $type = 'HEADER';

            public function getValue(): mixed
            {
                return null;
            }
        };
        $this->assertNull($param->getParamType());
    }

    public function testInputTypeEnumAndStringAreEquivalent(): void
    {
        $validator = new InputValidator();
        $this->assertTrue($validator->validate(5, InputType::Integer)->isOk());
        $this->assertFalse($validator->validate('x', InputType::Integer)->isOk());
        $this->assertSame($validator->validate('x', InputType::INTEGER)->getErrors(), $validator->validate('x', InputType::Integer)->getErrors());
        $this->assertSame(5, $validator->transformType('5', InputType::Integer));
        $this->assertSame(5, $validator->transformType('5', InputType::INTEGER));
        $this->assertSame('integer', InputType::INTEGER);
        $this->assertSame(InputType::Integer, InputType::from(InputType::INTEGER));
    }

    public function testInputParamAcceptsEnum(): void
    {
        $_GET['typed'] = 7;
        try {
            $param = new GetInputParam('typed', InputType::Integer);
            $this->assertTrue($param->validate()->isOk());
            $_GET['typed'] = 'x';
            $this->assertSame(['Value x has invalid type. Expected integer.'], $param->validate()->getErrors());
        } finally {
            unset($_GET['typed']);
        }
    }

    public function testBooleanTransformAcceptsIntOne(): void
    {
        $validator = new InputValidator();
        $this->assertTrue($validator->transformType(1, InputType::Boolean));
        $this->assertFalse($validator->transformType(0, InputType::Boolean));
    }

    public function testFloatAvailableValuesKeepStringKeys(): void
    {
        $param = new GetInputParam('a')->setAvailableValues([1.5, true]);
        $this->assertSame(['1.5' => 1.5, '1' => true], $param->getAvailableValues());
    }

    public function testAvailableValuesWithMultiAndArrayValues(): void
    {
        try {
            $_GET['av'] = ['a', 'b'];
            $multi = new GetInputParam('av')->setAvailableValues(['a', 'b'])->setMulti();
            $this->assertTrue($multi->validate()->isOk());

            $single = new GetInputParam('av')->setAvailableValues(['a', 'b']);
            $_GET['av'] = ['a'];
            $this->assertSame(['Field contains not available value(s)'], $single->validate()->getErrors());
        } finally {
            unset($_GET['av']);
        }
    }

    public function testJsonParamWithSeveralExamplesShowsExampleButtons(): void
    {
        $param = new JsonInputParam('body', '{"type": "object"}')->addExample('one', ['a' => 1])->addExample('two', '{"b": 2}');
        $param->updateConsoleForm(new Form());
        $this->assertStringContainsString('Select Example', $param->getDescription());
        $this->assertStringContainsString(htmlentities((string) json_encode(['b' => 2], JSON_PRETTY_PRINT)), $param->getDescription());

        $plain = new JsonInputParam('body', '{"type": "object"}');
        $plain->updateConsoleForm(new Form());
        $this->assertStringNotContainsString('Select Example', $plain->getDescription());
    }
}
