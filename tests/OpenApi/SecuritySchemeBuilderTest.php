<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\OpenApi;

use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\BasicAuthentication;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\CookieApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\NoAuthorization;
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;
use Tomaj\NetteApi\EndpointIdentifier;
use Tomaj\NetteApi\Misc\StaticIpDetector;
use Tomaj\NetteApi\Misc\StaticTokenRepository;
use Tomaj\NetteApi\OpenApi\SecuritySchemeBuilder;
use Tomaj\NetteApi\Test\Handler\TestHandler;
use Tomaj\NetteApi\Test\OpenApi\Fixtures\ApiFixtures;

class SecuritySchemeBuilderTest extends TestCase
{
    /**
     * @return array<string, array{ApiAuthorizationInterface, array<string, array<string, string>>}>
     */
    public static function provideAuthorizations(): array
    {
        $tokenRepository = new StaticTokenRepository();
        $ipDetector = new StaticIpDetector('127.0.0.1');
        return [
            'basic' => [new BasicAuthentication([], ApiFixtures::request()), ['Basic' => ['type' => 'http', 'scheme' => 'basic']]],
            'bearer' => [new BearerTokenAuthorization($tokenRepository, $ipDetector), ['Bearer' => ['type' => 'http', 'scheme' => 'bearer']]],
            'query' => [new QueryApiKeyAuthentication('api_key', $tokenRepository, $ipDetector), ['api_key__query__api_key' => ['type' => 'apiKey', 'in' => 'query', 'name' => 'api_key']]],
            'header' => [new HeaderApiKeyAuthentication('X-API-Key', $tokenRepository, $ipDetector), ['api_key__header__x_api_key' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'X-API-Key']]],
            'cookie' => [new CookieApiKeyAuthentication('api-token', $tokenRepository, $ipDetector), ['api_key__cookie__api_token' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'api-token']]],
        ];
    }

    /**
     * @dataProvider provideAuthorizations
     * @param array<string, array<string, string>> $expectedScheme
     */
    public function testSchemeAndRequirement(ApiAuthorizationInterface $authorization, array $expectedScheme): void
    {
        $builder = new SecuritySchemeBuilder();
        self::assertSame($expectedScheme, $builder->build([$this->createApi($authorization)]));
        self::assertSame([[array_key_first($expectedScheme) => []]], $builder->buildRequirement($authorization));
    }

    public function testNoAuthorizationHasNoScheme(): void
    {
        $builder = new SecuritySchemeBuilder();
        self::assertSame([], $builder->build([$this->createApi(new NoAuthorization())]));
        self::assertNull($builder->buildRequirement(new NoAuthorization()));
    }

    public function testSchemesAreDeduplicated(): void
    {
        $tokenRepository = new StaticTokenRepository();
        $ipDetector = new StaticIpDetector('127.0.0.1');
        $schemes = (new SecuritySchemeBuilder())->build([
            $this->createApi(new BearerTokenAuthorization($tokenRepository, $ipDetector)),
            $this->createApi(new NoAuthorization()),
            $this->createApi(new HeaderApiKeyAuthentication('X-Key', $tokenRepository, $ipDetector)),
            $this->createApi(new BearerTokenAuthorization($tokenRepository, $ipDetector)),
            $this->createApi(new HeaderApiKeyAuthentication('x_key', $tokenRepository, $ipDetector)),
        ]);

        self::assertSame(['Bearer', 'api_key__header__x_key'], array_keys($schemes));
        self::assertSame('x_key', $schemes['api_key__header__x_key']['name']); // last one wins
    }

    public function testRequirementContainsAllSchemesOfAuthorization(): void
    {
        $builder = new class extends SecuritySchemeBuilder {
            protected function createScheme(ApiAuthorizationInterface $authorization): array
            {
                return ['First' => ['type' => 'http', 'scheme' => 'basic'], 'Second' => ['type' => 'http', 'scheme' => 'bearer']];
            }
        };

        self::assertSame([['First' => [], 'Second' => []]], $builder->buildRequirement(new NoAuthorization()));
    }

    private function createApi(ApiAuthorizationInterface $authorization): Api
    {
        return new Api(new EndpointIdentifier('GET', '1', 'test'), new TestHandler(), $authorization);
    }
}
