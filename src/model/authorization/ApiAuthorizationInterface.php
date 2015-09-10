<?php

namespace Tomaj\NetteApi\Authorization;

interface ApiAuthorizationInterface
{
    public function authorized();

    public function getErrorMessage();

//    public function getAuthorizedResource();
}
