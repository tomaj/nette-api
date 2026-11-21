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
     * The pattern without its delimiters when it means the same in ECMA 262 (an OpenAPI `pattern`), otherwise null.
     * Only a plain subset qualifies: ASCII literals, escaped punctuation, \n \r \t, positive classes of literals and
     * ranges, groups, lookahead, alternation, anchors and counted or greedy and lazy quantifiers. Only the flags u and D
     * are allowed. \d \w and \b only without u, because PCRE makes them Unicode aware with u and ECMA 262 does not.
     * Everything else (the dot, negated classes, \s, \D, \p, \x, back references, inline flags, possessive quantifiers
     * and the rest of PCRE) reads differently or is a syntax error in a JavaScript validator.
     */
    public function expression(): ?string
    {
        $delimiter = $this->pattern[0];
        $closing = ['(' => ')', '{' => '}', '[' => ']', '<' => '>'][$delimiter] ?? $delimiter;
        $end = strrpos($this->pattern, $closing);
        assert($end !== false);
        $flags = substr($this->pattern, $end + 1);
        if (trim($flags, 'uD') !== '') {
            return null;
        }

        $expression = substr($this->pattern, 1, $end - 1);

        return preg_match(self::portable(str_contains($flags, 'u')), $expression) === 1 ? $expression : null;
    }

    /**
     * A PCRE pattern matching the portable subset described at expression(). (?<alt>) recurses for groups and lookaheads.
     */
    private static function portable(bool $unicode): string
    {
        // characters that are escaped in the expression to stand for themselves
        $punctuation = '\\\\^$.*+?()\[\]{}|\/';
        // a printable ASCII character that has no special meaning
        $literal = '[^\\\\.\[\](){}*+?|^$\x00-\x1F\x7F-\xFF]';
        // an escaped punctuation character, \n \r \t, and \d \w only where they are ASCII in PCRE
        $escape = '\\\\[' . $punctuation . 'nrt' . ($unicode ? '' : 'dw') . ']';
        // a positive character class of literals, escapes and ranges (the `-` is literal or a range in both dialects)
        $class = '\[(?!\^)(?:[^\\\\\[\]\x00-\x1F\x7F-\xFF]|\\\\[' . $punctuation . '\-nrt])+\]';
        // a literal, escape, class or (non-capturing) group with an optional greedy or lazy quantifier
        $atom = '(?:' . $literal . '|' . $escape . '|' . $class . '|\((?:\?:)?(?&alt)\))(?:[*+?]|\{\d+(?:,\d*)?\})?\??';
        // anchors, lookahead and \b only where it is ASCII in PCRE
        $assertion = '\^|\$|\((?:\?[=!])(?&alt)\)' . ($unicode ? '' : '|\\\\b');

        return '/\A(?<alt>(?<seq>(?:' . $atom . '|' . $assertion . ')*)(?:\|(?&seq))*)\z/';
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
