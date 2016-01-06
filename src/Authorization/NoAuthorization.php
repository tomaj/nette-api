<?php

namespace Tomaj\NetteApi\Authorization;

class NoAuthorization implements ApiAuthorizationInterface
{
    /**
     * {@inheritdoc}
     */
    public function authorized()
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorMessage()
    {
        return false;
    }
}
