<?php

namespace Tomaj\NetteApi\Authorization;

class NoAuthorization implements ApiAuthorizationInterface
{
    public function authorized()
    {
        return true;
    }

    public function getErrorMessage()
    {
        return false;
    }
}
