<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Response;

use Nette\Application\UI\ITemplate;
use Nette\Http\Request;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Response\TextApiResponse;

final class TextApiResponseTest extends TestCase
{
    public function testCreatingResponse(): void
    {
        $apiResponse = new TextApiResponse(200, 'hello');
        $this->assertSame(200, $apiResponse->getCode());

        $request = new Request(new UrlScript());
        $response = new Response();

        $this->expectOutputString('hello');
        $apiResponse->send($request, $response);
    }

    public function testRenderingTemplate(): void
    {
        $template = $this->createMock(ITemplate::class);
        $template->expects($this->once())->method('render');

        $apiResponse = new TextApiResponse(200, $template);

        $this->expectOutputString('');
        $apiResponse->send(new Request(new UrlScript()), new Response());
    }
}
