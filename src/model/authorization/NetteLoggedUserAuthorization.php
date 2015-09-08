<?php

namespace Tomaj\NetteApi\Authorization;

use Nette\Security\User;

class NetteLoggedAuthorization implements ApiAuthorizationInterface
{
    /** @var User  */
    private $user;

    private $errorMessage = false;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function authorized()
    {
        if ($this->user->isLoggedIn()) {
            return true;
        }

        $this->errorMessage = 'User not logged';
        return false;
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }
}
