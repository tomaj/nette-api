<?php

declare(strict_types=1);

namespace App;

use Nette\Http\IRequest;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;

/**
 * HTTP Basic authorization that reads the Authorization header.
 *
 * The bundled BasicAuthentication reads the user and password from the request URL,
 * which nette/http fills only up to version 3.1. Since nette/http 3.2 the URL has no
 * credentials, so use a class like this one, or install nette/http 3.1.
 * Nette API 4.0 reads the header in BasicAuthentication, so there this class is not needed.
 */
final class BasicCredentialsAuthorization implements ApiAuthorizationInterface
{
    /**
     * @param array<string, string> $users username => password
     */
    public function __construct(private array $users, private IRequest $request)
    {
    }

    public function authorized(): bool
    {
        $credentials = $this->request->getBasicCredentials();
        if ($credentials === null) {
            return false;
        }
        [$user, $password] = $credentials;
        // hash_equals compares in constant time, so the response time leaks nothing
        return isset($this->users[$user]) && hash_equals($this->users[$user], $password);
    }

    public function getErrorMessage(): ?string
    {
        return 'Incorrect username or password';
    }
}
