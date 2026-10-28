<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Response;

use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testScalarAndStringableData(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'str';
            }
        };

        $this->expectOutputString('421.5str');
        foreach ([42, 1.5, null, $stringable] as $data) {
            new TextApiResponse(200, $data)->send(new Request(new UrlScript()), new Response());
        }
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidDataProvider(): array
    {
        return [
            'array' => [['password' => 'hash'], 'array'],
            'object' => [new \stdClass(), 'stdClass'],
        ];
    }

    #[DataProvider('invalidDataProvider')]
    public function testNonStringableDataThrows(mixed $data, string $type): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TextApiResponse data must be a string, scalar, Stringable or ITemplate, ' . $type . ' given.');
        $this->expectOutputString('');
        new TextApiResponse(200, $data)->send(new Request(new UrlScript()), new Response());
    }
}
