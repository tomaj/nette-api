<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tomaj\NetteApi\Misc\CorsOriginAllowlist;
use Tomaj\NetteApi\Misc\CorsOriginCallback;
use Tomaj\NetteApi\Presenters\ApiPresenter;

final class ApiPresenterCorsPolicyTest extends TestCase
{
    private const string CONFLICT = 'setCorsAllowedOrigins() and setCorsOriginValidator() are both called, but only one of them can decide which origins are allowed. Keep one: list fixed origins with setCorsAllowedOrigins(), or check them in the validator, for example fn (string $origin): bool => in_array($origin, $fixedOrigins, true) || $this->isCustomerDomain($origin).';

    private static function policy(ApiPresenter $presenter): ?object
    {
        return new ReflectionProperty(ApiPresenter::class, 'corsAllowlist')->getValue($presenter);
    }

    public function testSameKindCanBeSetAgain(): void
    {
        $presenter = new ApiPresenter();
        $presenter->setCorsAllowedOrigins(['https://a.example.com']);
        $presenter->setCorsAllowedOrigins(['https://b.example.com']);
        $this->assertInstanceOf(CorsOriginAllowlist::class, self::policy($presenter));

        $presenter = new ApiPresenter();
        $presenter->setCorsOriginValidator(static fn (string $origin): bool => true);
        $presenter->setCorsOriginValidator(static fn (string $origin): bool => $origin === 'https://b.example.com');
        $policy = self::policy($presenter);
        $this->assertInstanceOf(CorsOriginCallback::class, $policy);
        $this->assertSame(['Vary' => 'Origin', 'Access-Control-Allow-Origin' => 'https://b.example.com'], $policy->headers('https://b.example.com'), 'no credentials by default, the last validator wins');
    }

    public function testValidatorAfterAllowlistFails(): void
    {
        $presenter = new ApiPresenter();
        $presenter->setCorsAllowedOrigins(['https://a.example.com']);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(self::CONFLICT);
        $presenter->setCorsOriginValidator(static fn (string $origin): bool => true);
    }

    public function testAllowlistAfterValidatorFails(): void
    {
        $presenter = new ApiPresenter();
        $presenter->setCorsOriginValidator(static fn (string $origin): bool => true);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(self::CONFLICT);
        $presenter->setCorsAllowedOrigins(['https://a.example.com']);
    }
}
