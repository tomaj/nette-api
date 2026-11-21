<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Link;

use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Input\EndpointTemplate;

class ApiLink
{
    private const string PACKAGE = 'nette-api-package';

    private const string ACTION = 'nette-api-action';

    /**
     * Create ApiLink
     */
    public function __construct(private readonly LinkGenerator $linkGenerator)
    {
    }

    /**
     * Create link to specified api endpoint
     *
     * A {name} segment of a URL template is filled from $params[name] (URL-encoded). Without a value it stays
     * literal, which is the OpenAPI path template.
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
     * The router would encode the slash and the braces of a template, so the link is made with stand-in values
     * of the package and the action, and the filled path replaces them.
     *
     * @param array<mixed> $params
     */
    private function templateLink(EndpointInterface $endpoint, array $params): string
    {
        $action = $endpoint->getApiAction();
        try {
            [$path, $params] = EndpointTemplate::fill(rtrim(sprintf('%s/%s', $endpoint->getPackage(), $action ?? ''), '/'), $params);
        } catch (\InvalidArgumentException $exception) {
            throw new InvalidLinkException(sprintf('Cannot build a link for %s %s. %s', $endpoint->getMethod(), $endpoint->getUrl(), $exception->getMessage()), previous: $exception);
        }

        $standIn = $action === null ? self::PACKAGE : self::PACKAGE . '/' . self::ACTION;
        $link = $this->linkGenerator->link('Api:Api:default', array_merge($params, [
            'version' => $endpoint->getVersion(),
            'package' => self::PACKAGE,
            'apiAction' => $action === null ? null : self::ACTION,
        ])) ?: '';
        $position = strpos($link, $standIn);
        if ($position === false) {
            throw new InvalidLinkException(sprintf(
                "Cannot build a link for %s %s. The router mask must have <package> and <apiAction> as path segments, for example '/api/v<version>/<package>[/<apiAction>]'.",
                $endpoint->getMethod(),
                $endpoint->getUrl(),
            ));
        }

        return substr_replace($link, $path, $position, strlen($standIn));
    }
}
