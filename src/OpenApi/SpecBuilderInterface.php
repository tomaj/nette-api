<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use InvalidArgumentException;
use Nette\Application\UI\InvalidLinkException;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Link\ApiLink;

/**
 * Creates the whole OpenAPI spec for apis of one version
 */
interface SpecBuilderInterface
{
    /**
     * @param Api[] $apis
     * @param string $baseUrl host url without trailing slash, e.g. https://example.com
     * @param array<string, mixed> $initData structured data merged recursively into the generated spec
     * @param array<string|int, mixed> $allowedEndpoints see EndpointFilter
     * @return array<array-key, mixed>
     * @throws InvalidLinkException
     * @throws InvalidArgumentException if two schemas contain definition with the same name but different content
     */
    public function build(array $apis, ?string $version, string $baseUrl, ApiLink $apiLink, array $initData = [], array $allowedEndpoints = []): array;
}
