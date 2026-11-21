<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Input\RequestInput;
use Tomaj\NetteApi\Input\UnsupportedMediaTypeException;
use Tomaj\NetteApi\Params\ParamsProcessor;
use Tomaj\NetteApi\Test\Input\Fixtures\ListUsersHandler;

final class RequestInputTest extends TestCase
{
    private array $server;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        RequestInput::reset();
        $_GET = [];
        $_POST = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        RequestInput::reset();
    }

    public function testARejectedMediaTypeIsRejectedAgainOnTheNextRead(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'text/plain';
        RequestInput::setRawBody('hello');

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                RequestInput::boot([MediaTypes::JSON], true, true);
                $this->fail(sprintf('attempt %d accepted text/plain', $attempt));
            } catch (UnsupportedMediaTypeException) {
                $this->assertNull(RequestInput::mediaType());
            }
        }
    }

    public function testEveryReadOfAHandlersParamsSeesTheCurrentRequest(): void
    {
        $_GET = ['page' => '2'];
        $this->assertSame(2, new ParamsProcessor(new ListUsersHandler()->params())->getValues()['page']);

        $_GET = ['page' => '5'];
        $this->assertSame(5, new ParamsProcessor(new ListUsersHandler()->params())->getValues()['page'], 'a worker or a test must not need RequestInput::reset() between two requests');
    }
}
