<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

use Nette\Utils\RegexpException;
use Nette\Utils\Strings;

/**
 * A complete PCRE match for a string. `$` matches only at the very end (the `D` modifier is always added),
 * so a trailing newline does not pass. An invalid pattern fails when the container is compiled.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Pattern implements ConstraintInterface
{
    private const string PCRE_ONLY = '/^(?:\\\\(?:[^AzZGhHRXKQEegNx]|x(?!\{))|[^\\\\])*?(?:\\\\(?:[AzZGhHRXKQEegN]|x\{)|[*+?]\+|\{\d+(?:,\d*)?\}\+|\(\?(?:[>P#|\'(&]|[-+]?\d|[a-zA-Z-]+[:)])|\[:[a-z]+:\])/';

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
     * The pattern without its delimiters, for an OpenAPI schema. Null when a flag (other than u or D)
     * changes the meaning, or the syntax is PCRE only, because an OpenAPI pattern is ECMA 262 without flags
     * and would reject values accepted here.
     */
    public function expression(): ?string
    {
        $delimiter = $this->pattern[0];
        $closing = ['(' => ')', '{' => '}', '[' => ']', '<' => '>'][$delimiter] ?? $delimiter;
        $end = strrpos($this->pattern, $closing);
        assert($end !== false);
        if (trim(substr($this->pattern, $end + 1), 'uD') !== '') {
            return null;
        }

        $expression = substr($this->pattern, 1, $end - 1);
        // PCRE-only syntax: \A \z \e \g \x{..}, possessive quantifiers, atomic groups, comments, branch reset, recursion, (?P<name>), inline flags and POSIX classes.
        // A backslash and the character it escapes are skipped as a pair, so `\\A` and `\++` are read the way PCRE reads them.
        if (preg_match(self::PCRE_ONLY, $expression) === 1) {
            return null;
        }

        return $expression;
    }

    #[\Override]
    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        if (!is_string($value)) {
            return 'Pattern applies to a string';
        }
        // D: `$` matches only at the very end, so "81101\n" does not pass /^[0-9]{5}$/ (as in an OpenAPI pattern)
        if (preg_match($this->pattern . 'D', $value) !== 1) {
            return 'Value must match the pattern ' . $this->pattern;
        }

        return null;
    }
}
