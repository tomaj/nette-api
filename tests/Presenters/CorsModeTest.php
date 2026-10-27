<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tomaj\NetteApi\Presenters\ApiPresenter;
use Tomaj\NetteApi\Presenters\CorsMode;

final class CorsModeTest extends TestCase
{
    public function testSetCorsHeaderStoresEnumValue(): void
    {
        $presenter = new ApiPresenter();
        $property = new ReflectionProperty(ApiPresenter::class, 'corsHeader');
        $presenter->setCorsHeader(CorsMode::Off);
        $this->assertSame('off', $property->getValue($presenter));
        $presenter->setCorsHeader('https://a.example.com');
        $this->assertSame('https://a.example.com', $property->getValue($presenter));
    }
}
