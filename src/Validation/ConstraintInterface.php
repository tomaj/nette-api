<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

/**
 * One validation rule for a converted DTO property.
 * Return null when the value is acceptable, or one message when it is not.
 * Do not throw for ordinary invalid client input.
 */
interface ConstraintInterface
{
    public function validate(mixed $value, ConstraintContext $context): ?string;
}
