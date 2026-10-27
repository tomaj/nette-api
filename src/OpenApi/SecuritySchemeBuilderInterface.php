<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;

/**
 * Creates security schemes and security requirements of operations
 */
interface SecuritySchemeBuilderInterface
{
    /**
     * @param Api[] $apis
     * @return array<string, array<string, mixed>> security schemes used by apis indexed by scheme name
     */
    public function build(array $apis): array;

    /**
     * @return array<int, array<string, array<mixed>>>|null security requirement of operation, null if authorization has no security scheme
     */
    public function buildRequirement(ApiAuthorizationInterface $authorization): ?array;
}
