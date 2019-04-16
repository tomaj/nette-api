<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use League\Fractal\Manager;
use League\Fractal\ScopeFactoryInterface;
use Nette\Application\LinkGenerator;
use Nette\Application\UI\InvalidLinkException;
use Nette\InvalidStateException;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Response\ResponseInterface;

abstract class BaseHandler implements ApiHandlerInterface
{
    /**
     * @var Manager|null
     */
    private $fractal;

    /**
     * @var EndpointInterface|null
     */
    private $endpoint;

    /**
     * @var LinkGenerator|null
     */
    protected $linkGenerator;

    public function __construct(ScopeFactoryInterface $scopeFactory = null)
    {
        $this->fractal = new Manager($scopeFactory);
    }

    /**
     * {@inheritdoc}
     */
    public function summary(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function description(): string
    {
        return '';
    }

    /**
     * {@inheritdoc}
     */
    public function params(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function tags(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function deprecated(): bool
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function outputs(): array
    {
        return [];
    }

    protected function getFractal(): Manager
    {
        if (!$this->fractal) {
            throw new InvalidStateException("Fractal manager isn't initialized. Did you call parent::__construct() in your handler constructor?");
        }
        return $this->fractal;
    }

    /**
     * {@inheritdoc}
     */
    final public function setEndpointIdentifier(EndpointInterface $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    final public function getEndpoint(): ?EndpointInterface
    {
        return $this->endpoint;
    }

    /**
     * Set link generator to handler
     *
     * @param LinkGenerator $linkGenerator
     *
     * @return self
     */
    final public function setupLinkGenerator(LinkGenerator $linkGenerator): self
    {
        $this->linkGenerator = $linkGenerator;
        return $this;
    }

    /**
     * Create link to actual handler endpoint
     *
     * @param array   $params
     *
     * @return string
     * @throws InvalidLinkException if handler doesn't have linkgenerator or endpoint
     */
    final public function createLink(array $params = []): string
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
    abstract public function handle(array $params): ResponseInterface;
}
