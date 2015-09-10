<?php

namespace Tomaj\NetteApi\Link;

use Nette\Application\LinkGenerator;
use Tomaj\NetteApi\EndpointIdentifier;

class ApiLink
{
    public function __construct(LinkGenerator $linkGenerator)
    {
        $this->linkGenerator = $linkGenerator;
    }

    public function link(EndpointIdentifier $endpoint, $params)
    {
//        return $this->linkGenerator->link('Api:Api:default', ['version' => 1, 'package' => '123213', 'apiAction' => null]);
        $params = array_merge(['version' => $endpoint->getVersion(), 'package' => $endpoint->getPackage(), 'apiAction' => $endpoint->getApiAction()], $params);
        return $this->linkGenerator->link('Api:Api:default', $params);
    }
}
