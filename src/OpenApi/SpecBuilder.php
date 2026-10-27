<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use InvalidArgumentException;
use Nette\Application\UI\InvalidLinkException;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Link\ApiLink;

/**
 * Creates the whole OpenAPI spec (info, servers, components and paths) for apis of one version
 */
class SpecBuilder implements SpecBuilderInterface
{
    protected PathBuilderInterface $pathBuilder;

    public function __construct(?PathBuilderInterface $pathBuilder = null)
    {
        $this->pathBuilder = $pathBuilder ?? new PathBuilder();
    }

    /**
     * @param Api[] $apis all apis, only apis of $version are included
     * @param string $baseUrl host url without trailing slash, e.g. https://example.com
     * @param ApiLink $apiLink creates links (paths) of apis
     * @param array<string, mixed> $initData structured data merged recursively into the generated spec
     * @param array<string|int, mixed> $allowedEndpoints see EndpointFilter
     * @return array<string, mixed>
     * @throws InvalidLinkException
     * @throws InvalidArgumentException if two schemas contain definition with the same name but different content
     */
    public function build(array $apis, ?string $version, string $baseUrl, ApiLink $apiLink, array $initData = [], array $allowedEndpoints = []): array
    {
        $apis = array_filter($apis, fn(Api $api): bool => ($version ?? '') === $api->getEndpoint()->getVersion());
        $basePath = $this->pathBuilder->buildBasePath($apis, $baseUrl, $apiLink);
        $securitySchemes = $this->pathBuilder->buildSecuritySchemes($apis);
        $schemaRegistry = new SchemaRegistry();

        $data = [
            'openapi' => '3.0.0',
            'info' => [
                'version' => $version,
                'title' => 'Nette API',
            ],
            'servers' => [
                [
                    'url' => $baseUrl . $basePath,
                ],
            ],
            'components' => [
                'securitySchemes' => $securitySchemes,
                'schemas' => $this->pathBuilder->buildErrorSchemas(),
            ],
            'paths' => $this->pathBuilder->build($apis, $baseUrl, $basePath, $apiLink, new EndpointFilter($allowedEndpoints), $schemaRegistry),
        ];

        if (!$securitySchemes) {
            unset($data['components']['securitySchemes']);
        }

        if (!empty($schemaRegistry->getDefinitions())) {
            // array_replace keeps numeric definition names (array_merge would renumber them)
            $data['components']['schemas'] = array_replace($schemaRegistry->getDefinitions(), $data['components']['schemas']);
        }

        return array_replace_recursive($data, $initData);
    }
}
