<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Logger;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tomaj\NetteApi\Logger\LogMaskMode;
use Tomaj\NetteApi\Logger\LogValueMasker;

final class LogValueMaskerTest extends TestCase
{
    public static function values(): iterable
    {
        yield 'full' => [LogMaskMode::Full, 'Bearer abcdefghijklmnop', '***'];
        yield 'full empty' => [LogMaskMode::Full, '', '***'];
        yield 'partial long' => [LogMaskMode::Partial, 'abcdefghijkl', 'abcd***'];
        yield 'partial short' => [LogMaskMode::Partial, 'abcdefghijk', '***'];
        yield 'partial scheme long' => [LogMaskMode::Partial, 'Bearer abcdefghijklmnop', 'Bearer abcd***'];
        yield 'partial scheme short' => [LogMaskMode::Partial, 'Basic dXNlcg==', 'Basic ***'];
        yield 'partial scheme spaces' => [LogMaskMode::Partial, "bearer \t abcdefghijklmnop", "bearer \t abcd***"];
        yield 'partial long scheme' => [LogMaskMode::Partial, 'AWS4-HMAC-SHA256 Credential=abcdefgh', 'AWS4-HMAC-SHA256 Cred***'];
        yield 'partial unknown word is not a scheme' => [LogMaskMode::Partial, 'secretpart rest', 'secr***'];
        yield 'partial scheme must be whole word' => [LogMaskMode::Partial, 'Bearerx abcdefghijklmnop', 'Bear***'];
        yield 'partial scheme not in the middle' => [LogMaskMode::Partial, 'x Bearer abcdefghijklmnop', 'x Be***'];
        yield 'partial multiline' => [LogMaskMode::Partial, "Bearer abcdefghijkl\nmore", 'Bearer abcd***'];
        yield 'partial scheme only' => [LogMaskMode::Partial, 'Bearer ', '***'];
        yield 'hash' => [LogMaskMode::Hash, 'secret', 'hash:' . substr(hash('sha256', 'secret'), 0, 16)];
        yield 'omit' => [LogMaskMode::Omit, 'secret', null];
    }

    #[DataProvider('values')]
    public function testMask(LogMaskMode $mode, string $value, ?string $expected): void
    {
        $masker = new LogValueMasker($mode);
        $this->assertSame($mode, $masker->getMode());
        $this->assertSame($expected, $masker->mask($value));
    }

    public function testDefaultIsFull(): void
    {
        $this->assertSame(LogMaskMode::Full, new LogValueMasker()->getMode());
        $this->assertSame('***', LogValueMasker::MASK);
    }

    public function testHashWithKeyUsesHmac(): void
    {
        $masker = new LogValueMasker(LogMaskMode::Hash, 'k3y');
        $this->assertSame('hash:' . substr(hash_hmac('sha256', 'secret', 'k3y'), 0, 16), $masker->mask('secret'));
        $this->assertSame($masker->mask('secret'), $masker->mask('secret'), 'the same value gives the same hash for correlation');
        $this->assertNotSame($masker->mask('secret'), $masker->mask('secret2'));
    }

    public function testHashKeyWithoutHashFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('new LogValueMasker(LogMaskMode::Partial, hashKey): the hash key is used only by LogMaskMode::Hash. Use new LogValueMasker(LogMaskMode::Hash, $key), or leave the key out.');
        new LogValueMasker(LogMaskMode::Partial, 'k3y');
    }

    public function testEmptyHashKeyFails(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('new LogValueMasker(LogMaskMode::Hash, hashKey): the hash key is empty, so it would not protect short secrets.');
        new LogValueMasker(LogMaskMode::Hash, '');
    }

    public function testModeValues(): void
    {
        $this->assertSame(['full', 'partial', 'hash', 'omit'], array_map(static fn (LogMaskMode $mode): string => $mode->value, LogMaskMode::cases()));
    }
}
