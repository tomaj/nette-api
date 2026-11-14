<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Link;

use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Tomaj\NetteApi\EndpointInterface;

class ApiLink
{
    /**
     * Create ApiLink
     */
    public function __construct(private readonly LinkGenerator $linkGenerator)
    {
    }

    /**
     * Create link to specified api endpoint
     *
     * A {name} segment of a URL template stays literal, which is the OpenAPI path template.
     *
     * @param array<mixed>       $params
     * @throws InvalidLinkException
     */
    public function link(EndpointInterface $endpoint, $params = []): string
    {
        $package = $endpoint->getPackage();
        $action = $endpoint->getApiAction();
        if (str_contains($package, '{') || str_contains($package, '/') || ($action !== null && (str_contains($action, '{') || str_contains($action, '/')))) {
            return $this->templateLink($endpoint, $params);
        }

        $params = array_merge([
            'version' => $endpoint->getVersion(),
            'package' => $package,
            'apiAction' => $action,
        ], $params);
        return $this->linkGenerator->link('Api:Api:default', $params) ?: '';
    }

    /**
     * Keeps {name} segments literal. A normal route parameter would encode the slash and the braces.
     *
     * @param array<mixed> $params
     */
    private function templateLink(EndpointInterface $endpoint, array $params): string
    {
        $sample = $this->linkGenerator->link('Api:Api:default', [
            'version' => $endpoint->getVersion(),
            'package' => 'package',
            'apiAction' => 'action',
        ]) ?: '';
        $needle = sprintf('/v%s/package/action', $endpoint->getVersion());
        $position = strpos($sample, $needle);
        if ($position === false) {
            throw new InvalidLinkException(sprintf(
                "Cannot build a link for %s %s. The router mask must contain version, package and apiAction, for example '/api/v<version>/<package>[/<apiAction>]'.",
                $endpoint->getMethod(),
                $endpoint->getUrl(),
            ));
        }

        $url = substr($sample, 0, $position) . '/' . $endpoint->getUrl();
        unset($params['version'], $params['package'], $params['apiAction']);
        if ($params !== []) {
            $url = sprintf('%s?%s', $url, http_build_query($params));
        }

        return $url;
    }
}
