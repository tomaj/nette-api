<?php

namespace Tomaj\NetteApi\Params;

interface ParamInterface
{
    public function isValid(): bool;

    public function getKey(): string;

    public function getValue();
}
