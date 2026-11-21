<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

/**
 * A string must be an email address.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Email implements ConstraintInterface
{
    #[\Override]
    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return 'Email must be a valid email address';
        }

        return null;
    }
}
