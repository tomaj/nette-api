<?php

namespace Tomaj\NetteApi\Link;

use Nette\Application\LinkGenerator;
use Tomaj\NetteApi\EndpointIdentifier;

class ApiLink
{
    /**
     * @var LinkGenerator
     */
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
     * @param EndpointIdentifier  $endpoint
     * @param array               $params
     *
     * @return string
     */
    public function link(EndpointIdentifier $endpoint, $params = [])
    {
        $params = array_merge([
            'version' => $endpoint->getVersion(),
            'package' => $endpoint->getPackage(),
            'apiAction' => $endpoint->getApiAction()
        ], $params);
        return $this->linkGenerator->link('Api:Api:default', $params);
    }
}
