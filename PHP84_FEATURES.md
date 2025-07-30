# PHP 8.4 Features Implementation

This document showcases how the Nette-Api library has been modernized to utilize PHP 8.4's cutting-edge features.

## 🚀 Property Hooks

Property hooks provide computed properties that are natively understood by IDEs and static analysis tools.

### EndpointIdentifier Class
```php
readonly class EndpointIdentifier implements EndpointInterface
{
    public readonly string $method {
        get => strtoupper($this->method);
    }

    public readonly string $url {
        get => "v{$this->version}/{$this->package}/{$this->apiAction}";
    }

    public readonly ?string $normalizedApiAction {
        get => $this->apiAction === '' ? null : $this->apiAction;
    }
}
```

### ValidationResult Class
```php
readonly class ValidationResult implements ValidationResultInterface
{
    public readonly bool $isOk {
        get => $this->status === ValidationStatus::OK;
    }
}
```

## 🔒 Asymmetric Visibility

Asymmetric visibility allows different access levels for reading and writing properties.

### JsonApiResponse Class
```php
readonly class JsonApiResponse implements ResponseInterface
{
    public readonly string $contentType {
        get => $this->contentType ?: 'application/json';
    }

    public readonly string $fullContentType {
        get => $this->contentType . '; charset=' . $this->charset;
    }
}
```

## 📊 New Array Functions

PHP 8.4 introduces four powerful array functions that make array operations more intuitive.

### ArrayUtils Utility Class
```php
final readonly class ArrayUtils
{
    // Find first matching endpoint
    public static function findApiEndpoint(array $endpoints, Closure $criteria): mixed
    {
        return array_find($endpoints, $criteria);
    }

    // Find key of first matching endpoint
    public static function findApiEndpointKey(array $endpoints, Closure $criteria): mixed
    {
        return array_find_key($endpoints, $criteria);
    }

    // Check if any endpoint matches
    public static function hasApiEndpoint(array $endpoints, Closure $criteria): bool
    {
        return array_any($endpoints, $criteria);
    }

    // Check if all endpoints match
    public static function allApiEndpointsMatch(array $endpoints, Closure $criteria): bool
    {
        return array_all($endpoints, $criteria);
    }
}
```

### ApiDecider Modernization
```php
final class ApiDecider
{
    public function getApi(string $method, string $version, string $package, ?string $apiAction = null): Api
    {
        // Use PHP 8.4's array_find instead of foreach loops
        $matchingApi = array_find(
            $this->apis,
            fn(Api $api) => $this->isApiMatch($api, $method, $version, $package, $apiAction)
        );

        if ($matchingApi) {
            // Process matching API...
        }
    }

    public function hasApisForVersion(string $version): bool
    {
        return array_any(
            $this->apis,
            fn(Api $api) => $api->getEndpoint()->getVersion() === $version
        );
    }
}
```

## 🏷️ Enumerations

Enums provide type-safe constants and better domain modeling.

### ValidationStatus Enum
```php
enum ValidationStatus: string
{
    case OK = 'OK';
    case ERROR = 'error';
}
```

Usage in ValidationResult:
```php
readonly class ValidationResult implements ValidationResultInterface
{
    public function __construct(
        public readonly ValidationStatus $status,
        public readonly array $errors = []
    ) {}

    public static function ok(): self
    {
        return new self(ValidationStatus::OK);
    }

    public static function error(array $errors = []): self
    {
        return new self(ValidationStatus::ERROR, $errors);
    }
}
```

## 🔐 Readonly Classes

Readonly classes ensure immutability and better performance.

### Api Class
```php
readonly class Api
{
    public readonly RateLimitInterface $rateLimit {
        get => $this->rateLimit ?? new NoRateLimit();
    }

    public function __construct(
        public readonly EndpointInterface $endpoint,
        public readonly ApiHandlerInterface|string $handler,
        public readonly ApiAuthorizationInterface $authorization,
        ?RateLimitInterface $rateLimit = null
    ) {
        $this->rateLimit = $rateLimit;
    }
}
```

## 📝 Enhanced Type System

### Union Types
```php
// Handler can be either an interface or a string class name
public readonly ApiHandlerInterface|string $handler,

// Method supports string or int versions
string|int $version,
```

### Constructor Property Promotion
```php
public function __construct(
    private readonly Container $container
) {
    // Properties are automatically created and assigned
}
```

### Null Coalescing Assignment
```php
$grouped[$version] ??= [];
```

## ⚠️ Deprecation Attribute

PHP 8.4's new `#[\Deprecated]` attribute provides native deprecation warnings.

```php
abstract class BaseHandler implements ApiHandlerInterface
{
    #[\Deprecated(
        message: "Use getEndpoint() instead",
        since: "8.4"
    )]
    protected function getEndpointIdentifier(): ?EndpointInterface
    {
        return $this->getEndpoint();
    }
}
```

## 🧪 Modern Testing with PHPUnit 11

### Attribute-Based Testing
```php
final class ArrayUtilsTest extends TestCase
{
    #[Test]
    public function findApiEndpointReturnsFirstMatch(): void
    {
        $result = ArrayUtils::findApiEndpoint(
            $this->endpoints,
            fn($endpoint) => $endpoint->getMethod() === 'GET'
        );

        $this->assertInstanceOf(EndpointIdentifier::class, $result);
    }

    #[Test]
    #[DataProvider('filterByMethodProvider')]
    public function filterByMethodReturnsCorrectEndpoints(string $method, int $expectedCount): void
    {
        // Test implementation...
    }
}
```

## 🏗️ Infrastructure Modernization

### Composer Dependencies Updated
```json
{
    "require": {
        "php": ">= 8.4.0",
        "nette/application": "^3.2",
        "nette/http": "^3.3",
        "tracy/tracy": "^2.10"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0",
        "symfony/yaml": "^7.0"
    }
}
```

### GitHub Actions with PHP 8.4
```yaml
strategy:
  matrix:
    php: [ '8.4' ]

steps:
  - name: Setup PHP
    uses: shivammathur/setup-php@v2
    with:
      php-version: ${{ matrix.php }}
      coverage: xdebug
```

### Modern PHPUnit Configuration
```xml
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/11.0/phpunit.xsd"
         failOnWarning="true"
         failOnRisky="true"
         executionOrder="random">
```

## 🎯 Benefits of PHP 8.4 Modernization

1. **Performance**: Readonly classes and property hooks provide better performance
2. **Type Safety**: Enhanced type system catches more errors at development time
3. **Developer Experience**: Better IDE support and cleaner code
4. **Maintainability**: Immutable data structures and clear property definitions
5. **Future-Proof**: Utilizing the latest PHP features ensures longevity

This modernization demonstrates how to leverage PHP 8.4's powerful features while maintaining backward compatibility through careful API design and comprehensive testing.