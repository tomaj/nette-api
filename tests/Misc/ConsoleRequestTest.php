<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Params;

use Tomaj\NetteApi\EndpointInterface;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Misc\ConsoleRequest;
use Tomaj\NetteApi\Handlers\EchoHandler;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Test\Input\Fixtures\AvatarHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\CreateOrderHandler;
use Tomaj\NetteApi\Test\Input\Fixtures\UserDetailHandler;
use Tomaj\NetteApi\Link\ApiLink;
use Nette\Application\LinkGenerator;
use Nette\Application\Routers\RouteList;
use Nette\Http\UrlScript;
use Nette\Http\FileUpload;
use Tomaj\NetteApi\Handlers\TypedHandler;
use Tomaj\NetteApi\Input\Body;
use Tomaj\NetteApi\Input\Consumes;
use Tomaj\NetteApi\Input\MediaTypes;
use Tomaj\NetteApi\Input\File;
use Tomaj\NetteApi\Input\FileUpload as InputFileUpload;
use Tomaj\NetteApi\Input\Header;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Validation\Length;
use Tomaj\NetteApi\Validation\Pattern;
use Tomaj\NetteApi\Response\ResponseInterface;

final readonly class ConsoleInnerInput
{
    public function __construct(#[Body] public int $b = 0)
    {
    }
}

final readonly class ConsoleMetaInput
{
    public function __construct(
        #[Body] public ?ConsoleInnerInput $a = null,
        #[Body] public bool $t = false,
        #[Body] public bool $f = false,
        #[Body] public ?string $n = null,
    ) {
    }
}

final readonly class ConsoleWireInput
{
    /**
     * @param list<int> $ids
     * @param list<bool> $flags
     */
    public function __construct(
        #[Body] public int $count = 0,
        #[Body] public float $ratio = 0.0,
        #[Body] public bool $on = false,
        #[Body] public bool $off = true,
        #[Body] public int $bad = 0,
        #[Body] public array $ids = [],
        #[Body] public array $flags = [],
        #[Body] public ?ConsoleMetaInput $meta = null,
        #[Body] public ?string $tags = null,
        #[Header('X-Tag')] public ?string $header = null,
        #[File] public ?InputFileUpload $doc = null,
    ) {
    }
}

final class ConsoleWireHandler extends TypedHandler
{
    public function handleDto(ConsoleWireInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ConsoleTextInput
{
    public function __construct(
        #[Body] #[Length(min: 3)] public string $name,
        #[Header('X-Need')] public string $need,
        #[Body] #[Pattern('/^[a-z]+$/i')] public string $ci = 'abc',
        #[Header('X-Other')] public ?string $other = null,
    ) {
    }
}

final class ConsoleTextHandler extends TypedHandler
{
    public function handleDto(ConsoleTextInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ConsoleFormInput
{
    public function __construct(
        #[Body] public int $count = 0,
        #[Body] public bool $on = false,
        #[Body] public ?ConsoleMetaInput $meta = null,
    ) {
    }
}

#[Consumes(MediaTypes::FORM)]
final class ConsoleFormHandler extends TypedHandler
{
    public function handleDto(ConsoleFormInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final readonly class ConsoleMultipartInput
{
    public function __construct(#[Body] public int $count = 0)
    {
    }
}

#[Consumes(MediaTypes::MULTIPART)]
final class ConsoleMultipartHandler extends TypedHandler
{
    public function handleDto(ConsoleMultipartInput $input): ResponseInterface
    {
        return new JsonApiResponse(200, []);
    }
}

final class ConsoleRequestTest extends TestCase
{
    public function testLogRequest(): void
    {
        $handler = new EchoHandler();

        $request = new ConsoleRequest($handler);
        $response = $request->makeRequest("http://127.0.0.1:23523/", 'POST', ['status' => 'ok', 'message' => 'Hello']);

        $this->assertTrue($response->isError());
        $this->assertSame('http://127.0.0.1:23523/?status=ok&message=Hello', $response->getUrl());
    }

    public function testMultiValueKeysAreMappedToTheirParam(): void
    {
        $request = new ConsoleRequest(new EchoHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', ['status' => 'ok', 'message___1' => 'Hello']);

        $this->assertSame('http://127.0.0.1:23523/?status=ok&message=Hello', $response->getUrl());
    }

    public function testTypedPathAndHeaderGoToTheirSources(): void
    {
        $endpoint = new EndpointIdentifier('GET', 1, 'users', '{id}/detail');
        $request = new ConsoleRequest(new UserDetailHandler(), $endpoint, self::localLink());
        $response = $request->makeRequest('ignored', 'GET', ['id' => '4/2 x', 'limit' => '5', 'X-Request-Id' => "abc\r\nX-Evil: 1"]);

        $this->assertSame('http://127.0.0.1:23523/api/v1/users/4%2F2%20x/detail?limit=5', $response->getUrl());
        $this->assertSame(['X-Request-Id: abcX-Evil: 1'], $response->getHeaders());
        $this->assertSame([], $response->getPostFields());
        $this->assertNull($response->getRawPost());
    }

    public function testAnOverriddenLinkStillDecidesTheUrlWhenThereAreValuesForThePath(): void
    {
        $link = new class (self::localLinkGenerator()) extends ApiLink {
            public function link(EndpointInterface $endpoint, $params = []): string
            {
                return 'custom:' . parent::link($endpoint, $params);
            }
        };
        $detail = new EndpointIdentifier('GET', 1, 'users', '{id}/detail');

        // link() keeps its 3.x signature and leaves a {name} segment literal
        $this->assertSame('custom:http://127.0.0.1:23523/api/v1/users/{id}/detail', $link->link($detail));
        $this->assertSame('http://127.0.0.1:23523/api/v1/users/{id}/detail?x=1', self::localLink()->link($detail, ['x' => 1]));

        // the console fills the segments of whatever link() returned, with or without path values
        $request = new ConsoleRequest(new UserDetailHandler(), $detail, $link);
        $this->assertSame('custom:http://127.0.0.1:23523/api/v1/users/7/detail?limit=5', $request->makeRequest('ignored', 'GET', ['id' => '7', 'limit' => '5'])->getUrl());
        $request = new ConsoleRequest(new EchoHandler(), new EndpointIdentifier('POST', 1, 'users', 'create'), $link);
        $this->assertStringStartsWith('custom:http://127.0.0.1:23523/api/v1/users/create', $request->makeRequest('ignored', 'POST', [])->getUrl());
    }

    public function testABlankPathValueIsNotSentButExplained(): void
    {
        foreach (['', '  '] as $blank) {
            $request = new ConsoleRequest(new UserDetailHandler());
            $response = $request->makeRequest('http://127.0.0.1:23523/api/v1/users/{id}/detail', 'GET', ['id' => $blank, 'limit' => '5']);

            $this->assertTrue($response->isError());
            $this->assertSame("The path value 'id' is empty. Fill it in and send the request again.", $response->getErrorMessage());
            $this->assertSame(0, $response->getErrorNumber());
            $this->assertSame(0, $response->getResponseTime());
            $this->assertSame('http://127.0.0.1:23523/api/v1/users/{id}/detail', $response->getUrl());
            $this->assertSame('GET', $response->getMethod());
        }
    }

    public function testPathIsSubstitutedWithoutALinkGenerator(): void
    {
        $request = new ConsoleRequest(new UserDetailHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/api/v1/users/{id}/detail', 'GET', ['id' => '42']);

        $this->assertSame('http://127.0.0.1:23523/api/v1/users/42/detail', $response->getUrl());
    }

    public function testTypedBodyIsSentAsJsonWithWireTypes(): void
    {
        $request = new ConsoleRequest(new CreateOrderHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', [
            'customerEmail' => 'a@example.com',
            'address' => '{"street":"Main","postalCode":"12345"}',
            'items___0' => '{"sku":"A","quantity":2}',
            'items___1' => 'not json',
        ]);

        $this->assertSame('http://127.0.0.1:23523/', $response->getUrl());
        $this->assertSame(['Content-Type: application/json'], $response->getHeaders());
        $this->assertSame(
            '{"customerEmail":"a@example.com","address":{"street":"Main","postalCode":"12345"},"items":[{"sku":"A","quantity":2},"not json"]}',
            $response->getRawPost(),
        );
    }

    public function testTypedBodyWithAFileIsSentAsMultipart(): void
    {
        $file = new FileUpload(['name' => 'a.png', 'type' => 'image/png', 'size' => 3, 'tmp_name' => __FILE__, 'error' => UPLOAD_ERR_OK]);
        $request = new ConsoleRequest(new AvatarHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', ['displayName' => 'Jane', 'avatar' => $file]);

        $fields = $response->getPostFields();
        $this->assertSame(['avatar', 'displayName'], array_keys($fields));
        $this->assertInstanceOf(\CURLFile::class, $fields['avatar']);
        $this->assertSame('Jane', $fields['displayName']);
        $this->assertNull($response->getRawPost());
        $this->assertSame([], $response->getHeaders());
    }

    public function testBodyScalarsAreSentWithTheirWireTypes(): void
    {
        $request = new ConsoleRequest(new ConsoleWireHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', [
            'count' => ' 7 ', 'ratio' => '2', 'tags' => 'a/b č', 'on' => ' true ', 'off' => ' 0', 'bad' => '1.5',
            'ids___0' => '3', 'ids___1' => 'x', 'flags___0' => 'false', 'flags___1' => '1', 'flags___2' => 'maybe',
            'X-Tag' => 'a',
        ]);

        $this->assertSame(['X-Tag: a', 'Content-Type: application/json'], $response->getHeaders());
        $this->assertSame(
            '{"count":7,"ratio":2.0,"tags":"a/b č","on":true,"off":false,"bad":"1.5","ids":[3,"x"],"flags":[false,true,"maybe"]}',
            $response->getRawPost(),
        );
    }

    public function testBlankTypedFieldsAreNotSent(): void
    {
        $request = new ConsoleRequest(new ConsoleWireHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', [
            'count' => '', 'ratio' => ' ', 'on' => '', 'meta' => '', 'tags' => '',
            'ids___0' => '1', 'ids___1' => '', 'ids___2' => '2', 'ids___3' => '', 'ids___4' => '',
            'flags___0' => '', 'X-Tag' => '',
        ]);

        // an optional text field is left to the server default as well
        $this->assertSame(['ids' => [1, 2]], json_decode((string) $response->getRawPost(), true));
        $this->assertSame(['Content-Type: application/json'], $response->getHeaders());
    }

    public function testARequiredTextFieldKeepsItsEmptyStringSoThatItsConstraintsCanBeTried(): void
    {
        $request = new ConsoleRequest(new ConsoleTextHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', ['name' => '', 'ci' => '', 'X-Need' => '', 'X-Other' => ' ']);

        // ci has a default and a pattern, so the server decides; a blank header is never sent
        $this->assertSame('{"name":""}', $response->getRawPost());
        $this->assertSame(['Content-Type: application/json'], $response->getHeaders());
    }

    public function testBlankSlotsOfAListOfObjectsAreNotSent(): void
    {
        $request = new ConsoleRequest(new CreateOrderHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', [
            'items___0' => '{"sku":"a","quantity":1}', 'items___1' => '', 'items___2' => '',
        ]);

        $this->assertStringContainsString('"items":[{"sku":"a","quantity":1}]', (string) $response->getRawPost());
    }

    public function testANonStringBodyValueIsSentAsItIs(): void
    {
        $request = new ConsoleRequest(new ConsoleWireHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', ['count' => ['5']]);

        $this->assertSame('{"count":["5"]}', $response->getRawPost());
    }

    public function testNestedBodyIsFlattenedNextToAnUpload(): void
    {
        $file = new FileUpload(['name' => 'a.png', 'type' => 'image/png', 'size' => 3, 'tmp_name' => __FILE__, 'error' => UPLOAD_ERR_OK]);
        $request = new ConsoleRequest(new ConsoleWireHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', [
            'doc' => $file, 'meta' => '{"t":true,"a":{"b":1},"f":false,"n":null}', 'on' => 'nope',
        ]);

        $fields = $response->getPostFields();
        unset($fields['doc']);
        $this->assertSame(['meta[t]' => '1', 'meta[a][b]' => 1, 'meta[f]' => '0', 'on' => 'nope'], $fields);
        $this->assertNull($response->getRawPost());
    }

    public function testEndpointWithoutALinkGeneratorKeepsTheQueryAndExtraHeaders(): void
    {
        $endpoint = new EndpointIdentifier('GET', 1, 'users', '{id}/detail');
        $request = new ConsoleRequest(new UserDetailHandler(), $endpoint);
        $response = $request->makeRequest('http://127.0.0.1:23523/api/v1/users/{id}/detail', 'GET', ['id' => '7', 'limit' => '5'], ['headers' => ['X-Extra: 1']]);

        $this->assertSame('http://127.0.0.1:23523/api/v1/users/7/detail?limit=5', $response->getUrl());
        $this->assertSame(['X-Extra: 1'], $response->getHeaders());
    }

    public function testAFormOnlyHandlerReceivesAFormBody(): void
    {
        $request = new ConsoleRequest(new ConsoleFormHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', ['count' => '3', 'on' => 'true', 'meta' => '{"t":true,"a":{"b":4}}']);

        $this->assertSame(['Content-Type: application/x-www-form-urlencoded'], $response->getHeaders());
        parse_str((string) $response->getRawPost(), $sent);
        $this->assertSame(['count' => '3', 'on' => '1', 'meta' => ['t' => '1', 'a' => ['b' => '4']]], $sent);
        $this->assertSame([], $response->getPostFields());
    }

    public function testAMultipartOnlyHandlerReceivesMultipartFieldsWithoutAFile(): void
    {
        $request = new ConsoleRequest(new ConsoleMultipartHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', ['count' => '3']);

        $this->assertSame(['count' => 3], $response->getPostFields());
        $this->assertNull($response->getRawPost());
        $this->assertSame([], $response->getHeaders());
    }

    public function testJsonStaysTheChoiceWhenItIsListedFirst(): void
    {
        $request = new ConsoleRequest(new ConsoleWireHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', ['count' => '3']);

        $this->assertSame(['Content-Type: application/json'], $response->getHeaders());
        $this->assertSame('{"count":3}', $response->getRawPost());
    }

    public function testNumbersThatJsonCannotHoldStayText(): void
    {
        $request = new ConsoleRequest(new ConsoleWireHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', ['count' => '99999999999999999999', 'ratio' => '1e999', 'tags' => "bad \xB1 byte"]);

        $this->assertSame('{"count":"99999999999999999999","ratio":"1e999","tags":"bad ' . "\u{FFFD}" . ' byte"}', $response->getRawPost());
    }

    public function testAnEmptyJsonObjectStaysAnObject(): void
    {
        $request = new ConsoleRequest(new ConsoleWireHandler());
        $response = $request->makeRequest('http://127.0.0.1:23523/', 'POST', ['meta' => '{}']);

        $this->assertSame('{"meta":{}}', $response->getRawPost());
        $list = $request->makeRequest('http://127.0.0.1:23523/', 'POST', ['ids___0' => '[1]', 'meta' => '[]']);
        $this->assertSame('{"ids":["[1]"],"meta":[]}', $list->getRawPost());
    }

    private static function localLink(): ApiLink
    {
        return new ApiLink(self::localLinkGenerator());
    }

    private static function localLinkGenerator(): LinkGenerator
    {
        $router = new RouteList();
        $router->addRoute('api/v<version>/<package>[/<apiAction>]', 'Api:Api:default');
        return new LinkGenerator($router, new UrlScript('http://127.0.0.1:23523/'));
    }
}
