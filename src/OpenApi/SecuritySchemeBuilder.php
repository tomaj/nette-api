<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\Authorization\BasicAuthentication;
use Tomaj\NetteApi\Authorization\BearerTokenAuthorization;
use Tomaj\NetteApi\Authorization\CookieApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\HeaderApiKeyAuthentication;
use Tomaj\NetteApi\Authorization\QueryApiKeyAuthentication;

/**
 * Creates security schemes (components.securitySchemes) and security requirements of operations from api authorizations
 *
 * To change or add schemes override createScheme(), it is used for both components and operations so they stay consistent.
 */
class SecuritySchemeBuilder implements SecuritySchemeBuilderInterface
{
    /**
     * @param Api[] $apis
     * @return array<string, array<string, mixed>> security schemes used by apis indexed by scheme name
     */
    final public function build(array $apis): array
    {
        $securitySchemes = [];
        foreach ($apis as $api) {
            foreach ($this->createScheme($api->getAuthorization()) as $name => $scheme) {
                $securitySchemes[$name] = $scheme;
            }
        }

        return $securitySchemes;
    }

    /**
     * @return array<int, array<string, array<mixed>>>|null security requirement of operation, null if authorization has no security scheme
     */
    final public function buildRequirement(ApiAuthorizationInterface $authorization): ?array
    {
        $scheme = $this->createScheme($authorization);
        if ($scheme === []) {
            return null;
        }

        return [
            array_map(fn(): array => [], $scheme),
        ];
    }

    /**
     * @return array<string, array<string, mixed>> scheme name => scheme object (all are required by operation), empty array if authorization has no security scheme
     */
    protected function createScheme(ApiAuthorizationInterface $authorization): array
    {
        if ($authorization instanceof BasicAuthentication) {
            return [
                'Basic' => [
                    'type' => 'http',
                    'scheme' => 'basic',
                ],
            ];
        }

        if ($authorization instanceof BearerTokenAuthorization) {
            return [
                'Bearer' => [
                    'type' => 'http',
                    'scheme' => 'bearer',
                ],
            ];
        }

        if ($authorization instanceof QueryApiKeyAuthentication) {
            return $this->createApiKeyScheme('query', $authorization->getQueryParamName());
        }

        if ($authorization instanceof HeaderApiKeyAuthentication) {
            return $this->createApiKeyScheme('header', $authorization->getHeaderName());
        }

        if ($authorization instanceof CookieApiKeyAuthentication) {
            return $this->createApiKeyScheme('cookie', $authorization->getCookieName());
        }

        return [];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function createApiKeyScheme(string $in, string $name): array
    {
        return [
            'api_key__' . $in . '__' . strtolower(str_replace('-', '_', $name)) => [
                'type' => 'apiKey',
                'in' => $in,
                'name' => $name,
            ],
        ];
    }
}
