<?php

declare(strict_types=1);

namespace App;

use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;

/**
 * Passes when any of the given authorizations passes, for example a bearer token or an API key.
 * When all fail, the error message lists why each one failed.
 */
final class AnyOfAuthorization implements ApiAuthorizationInterface
{
    /** @var ApiAuthorizationInterface[] */
    private array $authorizations;

    private ?string $errorMessage = null;

    public function __construct(ApiAuthorizationInterface ...$authorizations)
    {
        $this->authorizations = $authorizations;
    }

    public function authorized(): bool
    {
        $messages = [];
        foreach ($this->authorizations as $authorization) {
            if ($authorization->authorized()) {
                return true;
            }
            $messages[] = $authorization->getErrorMessage();
        }

        $this->errorMessage = implode('; ', array_filter($messages));
        return false;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
