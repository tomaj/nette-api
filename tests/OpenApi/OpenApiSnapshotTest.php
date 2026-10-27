<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use Nette\Application\LinkGenerator;
use Nette\Application\Routers\RouteList;
use Nette\Http\Response;
use Nette\Http\UrlScript;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Handlers\OpenApiHandler;
use Tomaj\NetteApi\Link\ApiLink;
use Tomaj\NetteApi\OpenApi\SpecBuilder;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\TextApiResponse;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ApiFixtures;

/**
 * Generated spec must stay byte-identical. Regenerate snapshots with UPDATE_SNAPSHOTS=1 only for intentional changes.
 */
class OpenApiSnapshotTest extends TestCase
{
    /**
     * @return array<string, array{string, string, array<string, mixed>, array<string|int, mixed>}>
     */
    public static function provideSpecs(): array
    {
        return [
            'v1 json' => ['1', 'json', [], []],
            'v1 yaml' => ['1', 'yaml', [], []],
            'v2 json' => ['2', 'json', [], []],
            'v1 init data and allowed endpoints' => [
                '1',
                'json',
                ['info' => ['title' => 'My API', 'description' => 'Custom'], 'servers' => [['url' => 'https://api.example.com']]],
                ['users' => ['get', 'PUT'], 'import/*' => 'post', 'ping'],
            ],
        ];
    }

    /**
     * @dataProvider provideSpecs
     * @param array<string, mixed> $initData
     * @param array<string|int, mixed> $allowedEndpoints
     */
    public function testSpecIsUnchanged(string $version, string $format, array $initData, array $allowedEndpoints): void
    {
        $apiDecider = ApiFixtures::apiDecider();
        $apiDecider->addApi(
            new EndpointIdentifier('GET', $version, 'docs', 'open-api'),
            new OpenApiHandler($apiDecider, ApiFixtures::apiLink(), ApiFixtures::request(), $initData, $allowedEndpoints),
            new NoAuthorization()
        );

        $response = $apiDecider->getApi('GET', $version, 'docs', 'open-api')->getHandler()->handle(['format' => $format]);
        self::assertSame(200, $response->getCode());

        if ($format === 'yaml') {
            self::assertInstanceOf(TextApiResponse::class, $response);
            ob_start();
            $response->send(ApiFixtures::request(), new Response());
            $output = (string) ob_get_clean();
        } else {
            self::assertInstanceOf(JsonApiResponse::class, $response);
            $output = json_encode($response->getPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
        }

        $this->assertMatchesSnapshot(preg_replace('/[^a-z0-9]+/', '-', $this->dataName()) . '.' . $format, $output);
    }

    public function testSpecWithoutEndpoint(): void
    {
        $handler = new OpenApiHandler(ApiFixtures::apiDecider(), ApiFixtures::apiLink(), ApiFixtures::request());
        $response = $handler->handle(['format' => 'json']);
        $output = json_encode($response->getPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        $this->assertMatchesSnapshot('no-endpoint.json', $output);
    }

    public function testCustomSpecBuilder(): void
    {
        $specBuilder = new class extends SpecBuilder {
            public function build(array $apis, ?string $version, string $baseUrl, ApiLink $apiLink, array $initData = [], array $allowedEndpoints = []): array
            {
                $spec = parent::build($apis, $version, $baseUrl, $apiLink, $initData, $allowedEndpoints);
                $spec['info']['x-generated-by'] = 'custom';
                return $spec;
            }
        };

        $handler = new OpenApiHandler(ApiFixtures::apiDecider(), ApiFixtures::apiLink(), ApiFixtures::request(), [], [], $specBuilder);
        $response = $handler->handle(['format' => 'json']);

        self::assertInstanceOf(JsonApiResponse::class, $response);
        self::assertSame('custom', $response->getPayload()['info']['x-generated-by']);
    }

    public function testApiLinkOfHandlerIsUsedWithInjectedSpecBuilder(): void
    {
        $router = new RouteList();
        $router->addRoute('admin-api/v<version>/<package>[/<apiAction>]', 'Api:Api:default');
        $adminApiLink = new ApiLink(new LinkGenerator($router, new UrlScript(ApiFixtures::BASE_URL)));

        $apiDecider = ApiFixtures::apiDecider();
        $apiDecider->addApi(
            new EndpointIdentifier('GET', '2', 'docs', 'open-api'),
            new OpenApiHandler($apiDecider, $adminApiLink, ApiFixtures::request(), [], [], new SpecBuilder()),
            new NoAuthorization()
        );

        $response = $apiDecider->getApi('GET', '2', 'docs', 'open-api')->getHandler()->handle(['format' => 'json']);

        self::assertSame(['/admin-api/v2/ping', '/admin-api/v2/users', '/admin-api/v2/docs/open-api'], array_keys($response->getPayload()['paths']));
    }

    private function assertMatchesSnapshot(string $name, string $actual): void
    {
        $file = __DIR__ . '/snapshots/' . $name;
        if (getenv('UPDATE_SNAPSHOTS')) {
            file_put_contents($file, $actual);
            self::markTestIncomplete('Snapshot ' . $name . ' was written, run tests again');
        }

        self::assertFileExists($file, 'Snapshot ' . $name . ' is missing, create it with UPDATE_SNAPSHOTS=1');
        self::assertSame(file_get_contents($file), $actual);
    }
}
