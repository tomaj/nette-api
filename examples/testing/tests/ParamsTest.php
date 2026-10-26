<?php

declare(strict_types=1);

namespace Tests;

use App\Api\ArticleDetailHandler;
use App\Api\ArticleRepository;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Params\ParamsProcessor;

/** ParamsProcessor is what ApiPresenter runs before handle(); GetInputParam reads $_GET. */
final class ParamsTest extends TestCase
{
    private ArticleDetailHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ArticleDetailHandler(new ArticleRepository());
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function testIdIsReadFromQuery(): void
    {
        $_GET = ['id' => '2'];

        $processor = new ParamsProcessor($this->handler->params());

        $this->assertFalse($processor->isError());
        $this->assertSame(['id' => '2'], $processor->getValues());
    }

    public function testMissingIdIsAnError(): void
    {
        $_GET = [];

        $processor = new ParamsProcessor($this->handler->params());

        $this->assertTrue($processor->isError());
        $this->assertSame(['id' => ['Field is required']], $processor->getErrors());
    }
}
