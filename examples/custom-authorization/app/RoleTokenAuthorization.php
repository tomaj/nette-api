<?php

declare(strict_types=1);

namespace App;

use Nette\Http\IRequest;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;

/**
 * Bearer token that maps to a user with roles. The endpoint passes when the user has the required role.
 */
final class RoleTokenAuthorization implements ApiAuthorizationInterface
{
    private ?string $errorMessage = null;

    /**
     * @param array<string, array{name: string, roles: string[]}> $tokens token => user
     */
    public function __construct(
        private array $tokens,
        private IRequest $request,
        private CurrentUser $currentUser,
        private ?string $requiredRole = null,
    ) {
    }

    public function authorized(): bool
    {
        $header = (string) $this->request->getHeader('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $match)) {
            $this->errorMessage = 'Missing bearer token';
            return false;
        }

        $user = null;
        // hash_equals() for every token, so the response time does not tell which one is close
        foreach ($this->tokens as $token => $data) {
            if (hash_equals((string) $token, $match[1])) {
                $user = new ApiUser($data['name'], $data['roles']);
            }
        }

        if ($user === null) {
            $this->errorMessage = 'Unknown token';
            return false;
        }

        if ($this->requiredRole !== null && !$user->hasRole($this->requiredRole)) {
            $this->errorMessage = "Role {$this->requiredRole} is required";
            return false;
        }

        $this->currentUser->set($user);
        return true;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
