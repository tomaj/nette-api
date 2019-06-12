<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Handlers;

use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Output\OutputInterface;
use Tomaj\NetteApi\Params\ParamInterface;
use Tomaj\NetteApi\Response\ResponseInterface;

interface ApiHandlerInterface
{
    /**
     * Summary of handler - short description of handler
     * @return string
     */
    public function summary(): string;

    /**
     * Description of handler
     * @return string
     */
    public function description(): string;

    /**
     * Returns available parameters that handler need
     *
     * @return ParamInterface[]
     */
    public function params(): array;

    /**
     * Returns list of tags for handler
     * @return array
     */
    public function tags(): array;

    /**
     * Marks handler as deprecated
     * @return bool
     */
    public function deprecated(): bool;

    /**
     * Main handle method that will be executed when api
     * endpoint contected with this handler will be triggered
     *
     * @param array $params
     *
     * @return ResponseInterface
     */
    public function handle(array $params): ResponseInterface;

    /**
     * Set actual endpoint identifier to hnadler.
     * It is neccesary for link creation.
     *
     * @param EndpointInterface $endpoint
     *
     * @return void
     */
    public function setEndpointIdentifier(EndpointInterface $endpoint): void;

    /**
     * List of possible outputs
     *
     * @return OutputInterface[]
     */
    public function outputs(): array;
}
