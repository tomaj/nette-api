<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Component;

use Nette\Application\UI\Form;
use Nette\Http\IRequest;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;
use Tomaj\NetteApi\EndpointInterface;
use Tomaj\NetteApi\Handlers\ApiHandlerInterface;
use Tomaj\NetteApi\Link\ApiLink;

interface ApiConsoleFormFactoryInterface
{
    public function create(
        IRequest $request,
        EndpointInterface $endpoint,
        ApiHandlerInterface $handler,
        ApiAuthorizationInterface $authorization,
        ?ApiLink $apiLink = null
    ): Form;
}
