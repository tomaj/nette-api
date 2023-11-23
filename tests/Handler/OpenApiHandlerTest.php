<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Handler;

use Nette\Application\LinkGenerator;
use Nette\Application\Routers\SimpleRouter;
use Nette\Http\Request;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\ApiDecider;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\OpenApiHandler;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\Test\Transformer\DummyTransformer;
use Tomaj\NetteApi\Test\Transformer\NoSchemaTransformer;
use Tomaj\NetteApi\Test\Transformer\NullSchemaTransformer;
use Tomaj\NetteApi\Test\Transformer\SameSchemaTransformer;

class OpenApiHandlerTest extends TestCase
{
    public function testReferencesGeneration()
    {
        $linkGenerator = new LinkGenerator(new SimpleRouter([]), new UrlScript('http://test/'));
        $apiLink = new ApiLink($linkGenerator);
        $request = new Request(new UrlScript('http://test/'));

        $dummyTransformer = new DummyTransformer();
        $noSchemaTransformer = new NoSchemaTransformer();
        $sameSchemaTransformer = new SameSchemaTransformer();
        $nullSchemaTransformer = new NullSchemaTransformer();

        $apiDecider = new ApiDecider();
        $apiDecider->addApi(
            new EndpointIdentifier('GET', 1, 'dummy'),
            new TransformerTestHandler($dummyTransformer),
            new NoAuthorization()
        );
        $apiDecider->addApi(
            new EndpointIdentifier('GET', 1, 'toodummy'),
            new TransformerTestHandler($dummyTransformer),
            new NoAuthorization()
        );
        $apiDecider->addApi(
            new EndpointIdentifier('GET', 1, 'sameschema'),
            new TransformerTestHandler($sameSchemaTransformer),
            new NoAuthorization()
        );
        $apiDecider->addApi(
            new EndpointIdentifier('GET', 1, 'noschema'),
            new TransformerTestHandler($noSchemaTransformer),
            new NoAuthorization()
        );
        $apiDecider->addApi(
            new EndpointIdentifier('GET', 1, 'nullschema'),
            new TransformerTestHandler($nullSchemaTransformer),
            new NoAuthorization()
        );

        $apiDecider->addApi(
            new EndpointIdentifier('GET', 1, 'docs', 'open-api'),
            new OpenApiHandler($apiDecider, $apiLink, $request, [$dummyTransformer, $noSchemaTransformer, $sameSchemaTransformer, $nullSchemaTransformer]),
            new NoAuthorization()
        );

        $result = $apiDecider->getApi('GET', 1, 'docs', 'open-api');
        $handler = $result->getHandler();

        $response = $handler->handle(['format' => 'json']);
        $this->assertEquals(200, $response->getCode());
        $payload = $response->getPayload();

        $this->assertEquals(6, count($payload['paths']));
        $this->assertEquals(5, count($payload['components']['schemas']));

        $def = array_values($payload['paths'])[0];
        $this->assertSame($def['get']['responses'][200]['content']['application/json; charset=utf-8']['schema']['$ref'], '#/components/schemas/Dummy');
        $this->assertSame($payload['components']['schemas']['Dummy']['properties']['id']['type'], 'string');

        $def = array_values($payload['paths'])[1];
        $this->assertSame($def['get']['responses'][200]['content']['application/json; charset=utf-8']['schema']['$ref'], '#/components/schemas/Dummy');
        $this->assertSame($payload['components']['schemas']['Dummy']['properties']['id']['type'], 'string');

        $def = array_values($payload['paths'])[2];
        $this->assertSame($def['get']['responses'][200]['content']['application/json; charset=utf-8']['schema']['$ref'], '#/components/schemas/Dummy');
        $this->assertSame($payload['components']['schemas']['Dummy']['properties']['id']['type'], 'string');

        $def = array_values($payload['paths'])[3];
        $this->assertSame($def['get']['responses'][200]['content']['application/json; charset=utf-8']['schema'], []);

        $def = array_values($payload['paths'])[4];
        $this->assertSame($def['get']['responses'][200]['content']['application/json; charset=utf-8']['schema']['$ref'], '#/components/schemas/NullSchema');
        $this->assertSame($payload['components']['schemas']['Dummy']['properties']['id']['type'], 'string');
    }
}
