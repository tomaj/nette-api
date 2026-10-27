<?php

declare(strict_types=1);

namespace App;

/**
 * One shared service: the authorization fills it, the handler reads it.
 * ApiPresenter calls authorized() before handle(), so the user is always set when the handler runs.
 */
final class CurrentUser
{
    private ?ApiUser $user = null;

    public function set(ApiUser $user): void
    {
        $this->user = $user;
    }

    public function get(): ?ApiUser
    {
        return $this->user;
    }
}
