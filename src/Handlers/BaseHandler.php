<?php

namespace Tomaj\NetteApi\Handlers;

use League\Fractal\Manager;
use Nette\Application\LinkGenerator;
use Nette\InvalidStateException;
use Tomaj\NetteApi\EndpointInterface;

abstract class BaseHandler implements ApiHandlerInterface
{
    /**
     * @var Manager
     */
    private $fractal;

    /**
     * @var EndpointInterface
     */
    private $endpoint;

    /**
     * @var  LinkGenerator
     */
    private $linkGenerator;

    public function __construct()
    {
        $this->fractal = new Manager();
    }

    /**
     * {@inheritdoc}
     */
    public function params()
    {
        return [];
    }

    protected function getFractal()
    {
        if (!$this->fractal) {
            throw new InvalidStateException("Fractal manager isnt initialized. Did you call parent::__construct() in your handler constructor?");
        }
        return $this->fractal;
    }

    /**
     * {@inheritdoc}
     */
    public function setEndpointIdentifier(EndpointInterface $endpoint)
    {
        $this->endpoint = $endpoint;
    }

    public function getEndpoint()
    {
        return $this->endpoint;
    }

    public function setupLinkGenerator(LinkGenerator $linkGenerator)
    {
        $this->linkGenerator = $linkGenerator;
        return $this;
    }

    public function createLink($params)
    {
        if (!$this->linkGenerator) {
            throw new InvalidStateException("You have setupLinkGenerator for this handler if you want to generate link in this handler");
        }
        if (!$this->endpoint) {
            throw new InvalidStateException("You have setEndpoint() for this handler if you want to generate link in this handler");
        }
        $params = array_merge([
            'version' => $this->endpoint->getVersion(),
            'package' => $this->endpoint->getPackage(),
            'apiAction' => $this->endpoint->getApiAction()
        ], $params);
        return $this->linkGenerator->link('Api:Api:default', $params);
    }

    /**
     * {@inheritdoc}
     */
    abstract public function handle($params);
}
