# UPGRADE

## Upgrade from 1.x to 2.0.0

### Removed support for old PHP versions
New version not supported PHP versions 5.6 and 7.0 and also hhvm. Please use it with newer versions of PHP (>7.1)

### Updated dependencies
Version 2.0.0 requires nette packages in version 3.0, so probably you will have to upgrade whole your nette application 

### Typehints
There are some breaking changes because of typehints:

#### ApiAuthorizationInterface
Add typehints to methods:
- `authorized(): bool`
- `getErrorMessage(): ?string`

#### ApiHandlerInterface
Add typehints to methods:
- `params(): array`
- `handle(array $params): Tomaj\NetteApi\Response\ResponseInterface`

#### ApiLoggerInterface
Add typehints to method:
- `log(int $responseCode, string $requestMethod, string $requestHeader, string $requestUri, string $requestIp, string $requestAgent, int $responseTime): bool`

#### BearerTokenRepositoryInterface
Add typehints to methods:
- `validToken(string $token): bool`
- `ipRestrictions(string $token): ?string`

#### IpDetectorInterface
Add typehints to method:
- `getRequestIp(): string`

#### ParamInterface
Add typehints to methods:
- `isValid(): bool`
- `getKey(): string`

#### EndpointInterface
Add typehints to methods:
- `getMethod(): string`
- `getVersion(): int`
- `getPackage(): string`
- `getApiAction(): ?string`
- `getUrl(): string`

### Renamed methods
Few methods have been renamed, please use their new versions:
- `ApiDecider::addApiHandler()` -> `ApiDecider::addApi()`
- `ApiDecider::getApiHandler()` -> `ApiDecider::getApi()`
- `ApiDecider::getHandlers()` -> `ApiDecider::getApis()`

### Final methods
BaseHandler now have few final methods:
- `setEndpointIdentifier` 
- `getEndpoint`
- `setupLinkGenerator`
- `createLink`

### Removed params
Parameters $parent and $name have been removed from ApiListingControl. New usage is:
```
new ApiListingControl($apiDecider)
```

### Changed params
Some parameters were strictly typed:
- second parameter in `JsonApiResponse::__construct` (`$payload` formerly known as `$data`) is now `array`
- fifth parameter in `JsonApiResponse::__construct` (`$expiration`) is now `DateTimeInteface` or `null`
- fourth parameter in `InputParam::__construct` (`$availableValues`) is now `array` or `null`

### Changed events
Registration of event onClick in ApiListingControl.
Use:
```
$apiListing->onClick[] = function ($method, $version, $package, $apiAction) {
    ...
};
```
