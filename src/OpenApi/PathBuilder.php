<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\OpenApi;

use Nette\Application\UI\InvalidLinkException;
use Tomaj\NetteApi\Api;
use Tomaj\NetteApi\Link\ApiLink;

/**
 * Creates paths (operations) of the spec from apis
 */
class PathBuilder
{
    private ParameterBuilder $parameterBuilder;

    private RequestBodyBuilder $requestBodyBuilder;

    private ResponseBuilder $responseBuilder;

    private SecuritySchemeBuilder $securitySchemeBuilder;

    public function __construct(
        ?ParameterBuilder $parameterBuilder = null,
        ?RequestBodyBuilder $requestBodyBuilder = null,
        ?ResponseBuilder $responseBuilder = null,
        ?SecuritySchemeBuilder $securitySchemeBuilder = null,
    ) {
        $this->parameterBuilder = $parameterBuilder ?? new ParameterBuilder();
        $this->requestBodyBuilder = $requestBodyBuilder ?? new RequestBodyBuilder();
        $this->responseBuilder = $responseBuilder ?? new ResponseBuilder();
        $this->securitySchemeBuilder = $securitySchemeBuilder ?? new SecuritySchemeBuilder();
    }

    /**
     * Longest common prefix of all api links without base url and trailing slash
     *
     * @param Api[] $apis
     * @param string $baseUrl host url without trailing slash, e.g. https://example.com
     * @throws InvalidLinkException
     */
    public function buildBasePath(array $apis, string $baseUrl, ApiLink $apiLink): string
    {
        $basePath = '';
        foreach ($apis as $api) {
            $basePath = $this->getLongestCommonSubstring($basePath, $apiLink->link($api->getEndpoint()));
        }

        return rtrim(str_replace($baseUrl, '', $basePath), '/');
    }

    /**
     * Security schemes of apis, created by the same SecuritySchemeBuilder as security requirements of operations
     *
     * @param Api[] $apis
     * @return array<string, array<string, mixed>>
     */
    public function buildSecuritySchemes(array $apis): array
    {
        return $this->securitySchemeBuilder->build($apis);
    }

    /**
     * Schemas referenced by default error responses of operations
     *
     * @return array<string, array<string, mixed>>
     */
    public function buildErrorSchemas(): array
    {
        return $this->responseBuilder->buildErrorSchemas();
    }

    /**
     * @param Api[] $apis
     * @param string $baseUrl host url without trailing slash, e.g. https://example.com
     * @param string $basePath prefix removed from paths, see buildBasePath()
     * @return array<string, array<string, array<string, mixed>>> path => method => operation
     * @throws InvalidLinkException
     */
    public function build(array $apis, string $baseUrl, string $basePath, ApiLink $apiLink, EndpointFilter $endpointFilter, SchemaRegistry $schemaRegistry): array
    {
        $list = [];
        foreach ($apis as $api) {
            $path = $this->removePrefix($this->removePrefix($apiLink->link($api->getEndpoint()), $baseUrl), $basePath);
            $endpointPath = str_replace('v' . $api->getEndpoint()->getVersion() . '/', '', $api->getEndpoint()->getUrl());
            if (!$endpointFilter->isAllowed($endpointPath, $api->getEndpoint()->getMethod())) {
                continue;
            }

            $list[$path][strtolower($api->getEndpoint()->getMethod())] = $this->buildOperation($api, $schemaRegistry);
        }

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildOperation(Api $api, SchemaRegistry $schemaRegistry): array
    {
        $handler = $api->getHandler();
        $settings = [
            'summary' => $handler->summary(),
            'description' => $handler->description(),
            'tags' => $handler->tags(),
        ];

        if ($handler->deprecated()) {
            $settings['deprecated'] = true;
        }

        $parameters = $this->parameterBuilder->build($handler);
        $requestBody = $this->requestBodyBuilder->build($handler, $schemaRegistry);
        // input of handler is validated, so 400 can be returned only if handler has params
        $responses = $this->responseBuilder->build($handler, $api->getAuthorization(), $handler->params() !== [], $schemaRegistry);

        if (!empty($parameters)) {
            $settings['parameters'] = $parameters;
        }

        if (!empty($requestBody)) {
            $settings['requestBody'] = $requestBody;
        }

        $security = $this->securitySchemeBuilder->buildRequirement($api->getAuthorization());
        if ($security !== null) {
            $settings['security'] = $security;
        }

        $settings['responses'] = $responses;
        return $settings;
    }

    private function removePrefix(string $path, string $prefix): string
    {
        return $prefix !== '' && str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    private function getLongestCommonSubstring(string $path1, string $path2): string
    {
        $commonSubstring = '';
        $shortest = min(strlen($path1), strlen($path2));
        for ($i = 0; $i <= $shortest; ++$i) {
            if (substr($path1, 0, $i) !== substr($path2, 0, $i)) {
                break;
            }

            $commonSubstring = substr($path1, 0, $i);
        }

        return $commonSubstring;
    }
}
