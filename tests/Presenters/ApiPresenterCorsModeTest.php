<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Presenters;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Presenters\ApiPresenter;
use Tomaj\NetteApi\Presenters\CorsMode;

final class ApiPresenterCorsModeTest extends TestCase
{
    #[DataProvider('provideAuto')]
    public function testAutoModeIsRenamed(string $mode): void
    {
        $presenter = new ApiPresenter();
        try {
            $presenter->setCorsHeader($mode);
            $this->fail('auto must throw');
        } catch (InvalidArgumentException $e) {
            $this->assertSame("The CORS mode 'auto' was renamed to 'reflect-unsafe' in 4.0. It is unsafe: it sends the origin of the Referer header back in Access-Control-Allow-Origin with Access-Control-Allow-Credentials: true, so any website can make requests with the user's cookies and read the responses. Safe replacement: setCorsAllowedOrigins(['https://app.example.com'], true) listing the origins that call the API; pass true only when the browser sends cookies or uses withCredentials. To keep the old behaviour anyway, use setCorsHeader(CorsMode::ReflectUnsafe) or setCorsHeader('reflect-unsafe'). See UPGRADE-4.0.md.", $e->getMessage());
        }
        $this->assertSame('off', new \ReflectionProperty($presenter, 'corsHeader')->getValue($presenter), 'the default stays');
    }

    /**
     * @return iterable<array{string}>
     */
    public static function provideAuto(): iterable
    {
        yield ['auto'];
        yield ['AUTO'];
    }

    public function testModesAreKept(): void
    {
        $presenter = new ApiPresenter();
        $property = new \ReflectionProperty($presenter, 'corsHeader');
        $this->assertSame('off', $property->getValue($presenter));
        $presenter->setCorsHeader(CorsMode::Any);
        $this->assertSame('*', $property->getValue($presenter));
        $presenter->setCorsHeader('https://autos.example.com');
        $this->assertSame('https://autos.example.com', $property->getValue($presenter));
        $presenter->setCorsHeader(CorsMode::ReflectUnsafe);
        $this->assertSame('reflect-unsafe', $property->getValue($presenter));
        $presenter->setCorsHeader('reflect-unsafe');
        $this->assertSame('reflect-unsafe', $property->getValue($presenter));
        $this->assertNull(CorsMode::tryFrom('auto'));
    }

    #[DataProvider('provideInvalidHeader')]
    public function testInvalidHeaderValueIsRejected(string $value): void
    {
        $presenter = new ApiPresenter();
        try {
            $presenter->setCorsHeader($value);
            $this->fail('an invalid value must throw');
        } catch (InvalidArgumentException $e) {
            $this->assertSame(sprintf("setCorsHeader('%s'): the value is neither a CORS mode nor an origin, it would be sent as Access-Control-Allow-Origin and no browser would accept it. Use 'off', '*', 'reflect-unsafe' (or a CorsMode case), or one origin in the form scheme://host[:port] without a path or trailing slash, for example setCorsHeader('https://app.example.com'); for more origins use setCorsAllowedOrigins().", $value), $e->getMessage());
        }
        $this->assertSame('off', new \ReflectionProperty($presenter, 'corsHeader')->getValue($presenter), 'the default stays');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideInvalidHeader(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase mode' => ['OFF'];
        yield 'null origin' => ['null'];
        yield 'no scheme' => ['app.example.com'];
        yield 'trailing slash' => ['https://app.example.com/'];
        yield 'list' => ['https://a.example.com, https://b.example.com'];
        yield 'header injection' => ["https://a.example.com\r\nSet-Cookie: x=1"];
    }

    public function testStringModesAreAccepted(): void
    {
        $presenter = new ApiPresenter();
        $property = new \ReflectionProperty($presenter, 'corsHeader');
        $presenter->setCorsHeader('*');
        $this->assertSame('*', $property->getValue($presenter));
        $presenter->setCorsHeader('off');
        $this->assertSame('off', $property->getValue($presenter));
        $presenter->setCorsHeader('http://localhost:5173');
        $this->assertSame('http://localhost:5173', $property->getValue($presenter));
    }
}
