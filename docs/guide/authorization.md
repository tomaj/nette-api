---
title: Authorization
description: Protect Nette API endpoints with bearer tokens, API keys in a header, query or cookie, IP restrictions, HTTP Basic or your own ApiAuthorizationInterface.
---

# Authorization

Every endpoint has an authorization object, the third argument of `addApi()`. It implements `ApiAuthorizationInterface` with two methods: `authorized(): bool` and `getErrorMessage(): ?string`.

<Diagram name="authorization-1" alt="ApiPresenter calls authorized(); false answers 401 with getErrorMessage(), true continues to params and the handler" />

When `authorized()` returns false, the error handler answers `401` with `{"status":"error","message":"..."}` and the message from `getErrorMessage()`. An exception thrown from `authorized()` answers `500` with the exception message.

## Built-in classes

| Class | Reads the token from | Constructor |
| --- | --- | --- |
| `NoAuthorization` | nothing, always passes | none |
| `BearerTokenAuthorization` | `Authorization: Bearer <token>` | `(TokenRepositoryInterface, IpDetectorInterface)` |
| `HeaderApiKeyAuthentication` | a header, for example `X-API-Key` | `(string $headerName, TokenRepositoryInterface, IpDetectorInterface)` |
| `QueryApiKeyAuthentication` | a query param, for example `?api_key=` | `(string $queryParamName, TokenRepositoryInterface, IpDetectorInterface)` |
| `CookieApiKeyAuthentication` | a cookie | `(string $cookieName, TokenRepositoryInterface, IpDetectorInterface)` |
| `BasicAuthentication` | the `Authorization: Basic` header (in 3.x the request URL, see below) | `(array $users, IRequest $httpRequest)` |

The token classes share `TokenAuthorization`: the token must be valid in the repository, then the client IP must pass the token's IP restriction.

## Tokens and IP restrictions

`TokenRepositoryInterface` has `validToken(string $token): bool` and `ipRestrictions(string $token): ?string`. `StaticTokenRepository` takes an array of token => restriction:

<<< @/../examples/auth/config/common.neon{yaml}

| Restriction | Means |
| --- | --- |
| `*` or empty string | any IP |
| `1.2.3.4` | exactly this IP |
| `1.2.3.4, 5.6.7.8` | any of the listed, separated by commas, spaces or new lines |
| `10.0.0.0/8` | an IPv4 range |
| `null` | never, `Invalid IP` |

For a real app, implement `TokenRepositoryInterface` on top of your database.

::: warning Known limits in 3.x
- **IP detection can be spoofed.** The bundled `IpDetector` prefers the `Client-IP` and `X-Forwarded-For` headers, which any client can send. The [rate limits](/guide/rate-limit#which-ip-is-counted) do not use it, they take the address from Nette; token IP restrictions and logs do. Use it only behind a proxy that overwrites them, or use your own detector like `RemoteAddrIpDetector` in the [authorization example](/examples/auth). This remains in 4.0: making `IpDetector` ignore the headers would silently change the detected IP for every app behind a proxy, so it needs a trusted-proxy setting and a deprecation step rather than a change inside a dependency upgrade. It makes sense in a minor release that adds the trusted-proxy option first. Tracked in [#234](https://github.com/tomaj/nette-api/issues/234).
- **Only the first range counts.** In a list, checking stops at the first entry with a `/`, so `10.0.0.0/8, 192.168.0.0/16` never matches the second range. Put the range last, or one range per token.
- **BasicAuthentication reads the user from the request URL**, which `nette/http` 3.2 and newer leaves empty, so in 3.x it never passes there. Write a class that reads `$request->getBasicCredentials()`. 4.0 reads the `Authorization: Basic` header.
- **Numeric tokens never match** in `StaticTokenRepository`: PHP turns a key like `'12345'` into an integer, which is never equal to the string token. Use keys with a letter.

Since 3.6.0, `BasicAuthentication` and `StaticTokenRepository` compare passwords and tokens with `hash_equals()`, in constant time, so the response time does not reveal how much of a token was right. Which values match is the same as before. In your own [token repository](#your-own-authorization) or authorization, compare secrets with `hash_equals()` too.
:::

## Your own authorization

```php
<?php

declare(strict_types=1);

namespace App\Api;

use Nette\Security\User;
use Tomaj\NetteApi\Authorization\ApiAuthorizationInterface;

final class AdminOnlyAuthorization implements ApiAuthorizationInterface
{
    public function __construct(private User $user) {}

    public function authorized(): bool
    {
        return $this->user->isInRole('admin');
    }

    public function getErrorMessage(): ?string
    {
        return 'Admins only';
    }
}
```

For a rate limit with `key: identity`, implement `AuthorizationIdentityInterface` too: `getIdentity()` returns who called (a user id, a token), or `null` before a successful `authorized()`. The built-in token authorizations return the token and `BasicAuthentication` the user name. A subclass that overrides their `authorized()` without calling the parent must override `getIdentity()` too. See [Rate limiting](/guide/rate-limit#before-and-after-authorization).

## FAQ

### What does the 401 body look like?

`{"status":"error","message":"Token doesn't exists or isn't active"}`, or another message from `getErrorMessage()`. You can change it in your own [error handler](/guide/errors).

### Can one endpoint accept a bearer token or an API key?

Write a class that tries both: call `authorized()` on each and remember which message to return. The [authorization example](/examples/auth) shows each method on its own endpoint.

### Does the console send my token?

Yes, the [web console](/guide/console) has a field for the bearer token, for header, query and cookie API keys, and user and password for Basic.

### How do I tell OpenAPI that an endpoint needs a token?

`OpenApiHandler` reads the authorization class and adds the matching security scheme. See [OpenAPI](/guide/openapi).

### Where do I limit how often a token may call the API?

That is a [rate limit](/guide/rate-limit), set per endpoint next to the authorization.
