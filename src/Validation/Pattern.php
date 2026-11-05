<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

use Nette\Utils\RegexpException;
use Nette\Utils\Strings;

/**
 * A complete PCRE match for a string. An invalid pattern fails when the container is compiled.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Pattern implements ConstraintInterface
{
    public function __construct(public string $pattern)
    {
        try {
            Strings::match('', $this->pattern);
        } catch (RegexpException $exception) {
            throw new \InvalidArgumentException(sprintf(
                "Pattern '%s' is not a valid PCRE pattern (%s). Use a delimited pattern, for example #[Pattern('/^[0-9]{5}$/')].",
                $this->pattern,
                $exception->getMessage(),
            ), previous: $exception);
        }
    }

    /**
     * The pattern without its delimiters and flags, for an OpenAPI schema.
     */
    public function expression(): string
    {
        $delimiter = $this->pattern[0];
        $end = strrpos($this->pattern, $delimiter);
        assert($end !== false);

        return substr($this->pattern, 1, $end - 1);
    }

    #[\Override]
    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        if (!is_string($value)) {
            return 'Pattern applies to a string';
        }
        if (preg_match($this->pattern, $value) !== 1) {
            return 'Pattern must match ' . $this->pattern;
        }

        return null;
    }
}
