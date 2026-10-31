<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Authorization;

use Nette\Http\IRequest;

class BasicAuthentication implements ApiAuthorizationInterface, AuthorizationIdentityInterface
{
    private ?string $identity = null;

    /**
     * @param array<array-key, mixed> $authentications - available username - password pairs (only string passwords can match)
     */
    public function __construct(private array $authentications, private readonly IRequest $httpRequest)
    {
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function authorized(): bool
    {
        // without credentials the empty password never matches, an empty configured password is rejected below
        [$user, $password] = $this->credentials() ?? ['', ''];
        $authentication = $this->authentications[$user] ?? null;
        if (!$authentication) {
            $this->identity = null;
            return false;
        }

        // non-string passwords (e.g. integers from neon) never matched with ===, keep that outcome
        $authorized = is_string($authentication) && hash_equals($authentication, $password);
        $this->identity = $authorized ? $user : null;
        return $authorized;
    }

    /**
     * User and password from the "Authorization: Basic" header. Nette puts PHP_AUTH_USER/PHP_AUTH_PW there,
     * the request URL has no user since nette/http 3.2.
     *
     * @return array{string, string}|null
     */
    private function credentials(): ?array
    {
        if (preg_match('~^Basic\s+(\S+)$~i', $this->httpRequest->getHeader('Authorization') ?? '', $match) !== 1) {
            return null;
        }
        $decoded = base64_decode($match[1], true);
        $parts = explode(':', (string) $decoded, 2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : null;
    }

    /**
     * {@inheritdoc}
     */
    /**
     * The user name of the last successful authorized()
     */
    #[\Override]
    public function getIdentity(): ?string
    {
        return $this->identity;
    }

    #[\Override]
    public function getErrorMessage(): ?string
    {
        return 'Incorrect username or password';
    }
}
