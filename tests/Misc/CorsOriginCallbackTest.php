<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Misc;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tomaj\NetteApi\Misc\CorsOriginCallback;
use Tomaj\NetteApi\Test\DI\Fixtures\CustomerOrigins;
use UnexpectedValueException;

final class CorsOriginCallbackTest extends TestCase
{
    private const string USAGE = 'Pass an object implementing Tomaj\NetteApi\Misc\CorsOriginValidatorInterface, or a callable like fn (string $origin): bool => in_array($origin, $origins, true).';

    public function testAllowedOriginIsReflectedExactly(): void
    {
        $seen = [];
        $callback = new CorsOriginCallback(function (string $origin) use (&$seen): bool {
            $seen[] = $origin;
            return str_ends_with($origin, '.customer.example.com');
        });

        $this->assertSame(['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'https://A.customer.example.com'], $callback->headers('https://A.customer.example.com'));
        $this->assertSame(['Vary' => 'Origin'], $callback->headers('https://evil.example.com'));
        $this->assertSame(['https://A.customer.example.com', 'https://evil.example.com'], $seen);
    }

    public function testCredentials(): void
    {
        $callback = new CorsOriginCallback(static fn (string $origin): bool => $origin === 'https://a.example.com', true);

        $this->assertSame(['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'https://a.example.com', 'Access-Control-Allow-Credentials' => 'true'], $callback->headers('https://a.example.com'));
        $this->assertSame(['Vary' => 'Origin'], $callback->headers('https://b.example.com'));
    }

    public function testInterface(): void
    {
        $callback = new CorsOriginCallback(new CustomerOrigins());

        $this->assertSame(['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'https://customer.example.com'], $callback->headers('https://customer.example.com'));
        $this->assertSame(['Vary' => 'Origin'], $callback->headers('https://other.example.com'));
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function provideNotOrigins(): iterable
    {
        yield 'none' => [null];
        yield 'null' => ['null'];
        yield 'empty' => [''];
        yield 'path' => ['https://a.example.com/'];
        yield 'list' => ['https://a.example.com, https://b.example.com'];
        yield 'space' => [' https://a.example.com'];
        yield 'header injection' => ["https://a.example.com\r\nSet-Cookie: x=1"];
        yield 'port too big' => ['https://a.example.com:65536'];
    }

    #[DataProvider('provideNotOrigins')]
    public function testOnlyOriginsReachTheValidator(?string $origin): void
    {
        $callback = new CorsOriginCallback(function (): bool {
            $this->fail('the validator must not be called');
        });

        $this->assertSame(['Vary' => 'Origin'], $callback->headers($origin));
    }

    public function testHighestPortReachesTheValidator(): void
    {
        $callback = new CorsOriginCallback(static fn (string $origin): bool => true);
        $this->assertSame('https://a.example.com:65535', $callback->headers('https://a.example.com:65535')['Access-Control-Allow-Origin'] ?? null);
    }

    public function testNonBoolResultFails(): void
    {
        $callback = new CorsOriginCallback(static fn (string $origin): mixed => 1);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage("The CORS origin validator returned int for origin 'https://a.example.com', but it must return true (allow the origin) or false (reject it). Return a bool, for example return in_array(\$origin, \$origins, true);");
        $callback->headers('https://a.example.com');
    }

    public function testNonBoolResultOfMixedTypeFails(): void
    {
        $callback = new CorsOriginCallback(static fn (string $origin): mixed => null);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('returned null for origin');
        $callback->headers('https://a.example.com');
    }

    public function testAcceptedReturnTypes(): void
    {
        $this->assertSame(['Vary' => 'Origin'], new CorsOriginCallback(static fn (string $origin): false => false)->headers('https://a.example.com'));
        $this->assertArrayHasKey('Access-Control-Allow-Origin', new CorsOriginCallback(static fn (string $origin): true => true)->headers('https://a.example.com'));
        $this->assertArrayHasKey('Access-Control-Allow-Origin', new CorsOriginCallback(static fn (string $origin, int $extra = 0): bool|int => true)->headers('https://a.example.com'));
        $this->assertArrayHasKey('Access-Control-Allow-Origin', new CorsOriginCallback(static fn (): bool => true)->headers('https://a.example.com'));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function provideInvalidValidators(): iterable
    {
        yield 'origin list' => [['https://a.example.com'], 'The CORS origin validator must be callable, array given (a list of origins goes to setCorsAllowedOrigins()). '];
        yield 'two origins' => [['https://a.example.com', 'https://b.example.com'], 'The CORS origin validator must be callable, array given (a list of origins goes to setCorsAllowedOrigins()). '];
        yield 'origin second' => [[5, 'https://b.example.com'], 'The CORS origin validator must be callable, array given (a list of origins goes to setCorsAllowedOrigins()). '];
        yield 'origin map' => [['app' => 'https://a.example.com'], 'The CORS origin validator must be callable, array given (a list of origins goes to setCorsAllowedOrigins()). '];
        yield 'origin string' => ['https://a.example.com', "The CORS origin validator must be callable, string given ('https://a.example.com' is not an existing function; a list of origins goes to setCorsAllowedOrigins()). "];
        yield 'static method' => ['App\\Cors::check', "The CORS origin validator must be callable, string given ('App\\Cors::check' is not an existing public static method). "];
        yield 'object method' => [[new stdClass(), 'check'], 'The CORS origin validator must be callable, array given (method stdClass::check() does not exist or is not public). '];
        yield 'class method' => [['App\\Cors', 'check'], 'The CORS origin validator must be callable, array given (method App\\Cors::check() does not exist or is not public). '];
        yield 'object' => [new stdClass(), 'The CORS origin validator must be callable, stdClass given (stdClass has no __invoke() method and does not implement CorsOriginValidatorInterface). '];
        yield 'bool' => [true, 'The CORS origin validator must be callable, bool given. '];
        yield 'three items' => [['a', 'b', 'c'], 'The CORS origin validator must be callable, array given. '];
        yield 'class not string' => [[5, 'check'], 'The CORS origin validator must be callable, array given. '];
        yield 'map' => [['class' => 'App\\Cors', 'method' => 'check'], 'The CORS origin validator must be callable, array given. '];
        yield 'method not string' => [['a', 5], 'The CORS origin validator must be callable, array given. '];
    }

    #[DataProvider('provideInvalidValidators')]
    public function testInvalidValidatorFails(mixed $validator, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message . (str_ends_with($message, ' ') ? self::USAGE : ''));
        new CorsOriginCallback($validator);
    }

    public function testInvalidClosuresFail(): void
    {
        $cases = [
            'The CORS origin validator requires 2 parameters, but it gets only the origin. ' => static fn (string $origin, string $path): bool => true,
            'The CORS origin validator returns string, but it must return bool: true allows the origin, false rejects it. ' => static fn (string $origin): string => $origin,
            'The CORS origin validator returns ?bool, but it must return bool: true allows the origin, false rejects it. ' => static fn (string $origin): ?bool => true,
            'The CORS origin validator returns void, but it must return bool: true allows the origin, false rejects it. ' => static function (string $origin): void {
            },
        ];
        foreach ($cases as $message => $validator) {
            try {
                new CorsOriginCallback($validator);
                $this->fail('expected an exception: ' . $message);
            } catch (InvalidArgumentException $e) {
                $this->assertSame($message . self::USAGE, $e->getMessage());
            }
        }
    }
}
