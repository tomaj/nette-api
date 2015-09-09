<?php

namespace Tomaj\NetteApi\Handlers;

use League\Fractal\Manager;

abstract class BaseHandler implements ApiHandlerInterface
{
    protected $fractal;

    public function __construct()
    {
        $this->fractal = new Manager();
    }

    public function params()
    {
        return [];
    }

    abstract public function handle($params);
}
