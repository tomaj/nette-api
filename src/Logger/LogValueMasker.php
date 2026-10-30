<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Logger;

final readonly class LogValueMasker implements LogValueMaskerInterface
{
    public const string MASK = '***';

    private const int PARTIAL_KEPT = 4;

    private const int PARTIAL_MIN_LENGTH = 12;

    private const int HASH_LENGTH = 16;

    /** HTTP authentication schemes kept in front of a partly masked value, compared case-insensitively */
    private const string SCHEMES = 'Basic|Bearer|Digest|DPoP|HOBA|Mutual|Negotiate|NTLM|Token|ApiKey|Hawk|AWS4-HMAC-SHA256|SCRAM-SHA-1|SCRAM-SHA-256|vapid';

    /**
     * @param string|null $hashKey only for LogMaskMode::Hash: a secret key for HMAC-SHA-256; without it a plain SHA-256 is used,
     *                             which can be reversed by trying all values of a short or guessable secret
     */
    public function __construct(
        private LogMaskMode $mode = LogMaskMode::Full,
        private ?string $hashKey = null,
    ) {
        if ($hashKey !== null && $mode !== LogMaskMode::Hash) {
            throw new \InvalidArgumentException(sprintf(
                "new LogValueMasker(LogMaskMode::%s, hashKey): the hash key is used only by LogMaskMode::Hash. Use new LogValueMasker(LogMaskMode::Hash, \$key), or leave the key out.",
                $mode->name,
            ));
        }
        if ($hashKey === '') {
            throw new \InvalidArgumentException('new LogValueMasker(LogMaskMode::Hash, hashKey): the hash key is empty, so it would not protect short secrets. Pass a long random secret, for example from an environment variable, or leave the key out for a plain SHA-256.');
        }
    }

    public function getMode(): LogMaskMode
    {
        return $this->mode;
    }

    #[\Override]
    public function mask(string $value): ?string
    {
        return match ($this->mode) {
            LogMaskMode::Full => self::MASK,
            LogMaskMode::Partial => $this->partial($value),
            LogMaskMode::Hash => 'hash:' . substr($this->hashKey === null ? hash('sha256', $value) : hash_hmac('sha256', $value, $this->hashKey), 0, self::HASH_LENGTH),
            LogMaskMode::Omit => null,
        };
    }

    private function partial(string $value): string
    {
        $scheme = '';
        if (preg_match('~^((?:' . self::SCHEMES . ')\s+)(\S.*)$~is', $value, $matches) === 1) {
            [, $scheme, $value] = $matches;
        }
        if (strlen($value) < self::PARTIAL_MIN_LENGTH) {
            return $scheme . self::MASK;
        }
        return $scheme . substr($value, 0, self::PARTIAL_KEPT) . self::MASK;
    }
}
