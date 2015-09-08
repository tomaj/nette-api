<?php

namespace Tomaj\NetteApi\Handlers;

interface ApiHandlerInterface
{
    public function params();

    public function handle($params);
}
