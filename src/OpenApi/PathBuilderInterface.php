<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Nette\Application\UI\InvalidLinkException;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Link\ApiLink;

/**
 * Creates paths, base path, security schemes and error schemas of the spec
 */
interface PathBuilderInterface
{
    /**
     * Longest common prefix of all api links without base url and trailing slash
     *
     * @param Api[] $apis
     * @param string $baseUrl host url without trailing slash, e.g. https://example.com
     * @throws InvalidLinkException
     */
    public function buildBasePath(array $apis, string $baseUrl, ApiLink $apiLink): string;

    /**
     * @param Api[] $apis
     * @return array<string, array<string, mixed>>
     */
    public function buildSecuritySchemes(array $apis): array;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function buildErrorSchemas(): array;

    /**
     * @param Api[] $apis
     * @param string $baseUrl host url without trailing slash, e.g. https://example.com
     * @param string $basePath prefix removed from paths, see buildBasePath()
     * @return array<string, array<string, array<string, mixed>>> path => method => operation
     * @throws InvalidLinkException
     */
    public function build(array $apis, string $baseUrl, string $basePath, ApiLink $apiLink, EndpointFilter $endpointFilter, SchemaRegistry $schemaRegistry): array;

    /**
     * @return array<string, mixed>
     */
    public function buildOperation(Api $api, SchemaRegistry $schemaRegistry): array;
}
