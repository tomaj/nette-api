<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Link;

use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Tomaj\NetteApi\EndpointInterface;

class ApiLink
{
    /** @var LinkGenerator */
    private $linkGenerator;

    /**
     * Create ApiLink
     *
     * @param LinkGenerator $linkGenerator
     */
    public function __construct(LinkGenerator $linkGenerator)
    {
        $this->linkGenerator = $linkGenerator;
    }

    /**
     * Create link to specified api endpoint
     *
     * @param EndpointInterface  $endpoint
     * @param array               $params
     *
     * @return string
     * @throws InvalidLinkException
     */
    public function link(EndpointInterface $endpoint, $params = [])
    {
        $params = array_merge([
            'version' => $endpoint->getVersion(),
            'package' => $endpoint->getPackage(),
            'apiAction' => $endpoint->getApiAction()
        ], $params);
        return $this->linkGenerator->link('Api:Api:default', $params);
    }
}
