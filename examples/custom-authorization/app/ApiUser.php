<?php

declare(strict_types=1);

namespace App;

/**
 * The caller, as found by the authorization. Handlers read it from CurrentUser.
 */
final class ApiUser
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        public readonly string $name,
        public readonly array $roles,
    ) {
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
