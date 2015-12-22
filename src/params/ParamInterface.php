<?php

namespace Tomaj\NetteApi\Params;

interface ParamInterface
{
    public function isValid();

    public function getKey();

    public function getValue();
}
