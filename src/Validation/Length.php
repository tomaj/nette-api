<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

/**
 * Unicode character count of a string, or the item count of a list. Either bound may be omitted.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Length implements ConstraintInterface
{
    public function __construct(
        public ?int $min = null,
        public ?int $max = null,
    ) {
        if ($this->min === null && $this->max === null) {
            throw new \InvalidArgumentException('Length needs min, max or both, for example #[Length(min: 1, max: 80)].');
        }
        if (($this->min !== null && $this->min < 0) || ($this->max !== null && $this->max < 0)) {
            throw new \InvalidArgumentException('Length bounds must be zero or greater, for example #[Length(min: 1, max: 80)].');
        }
        if ($this->min !== null && $this->max !== null && $this->min > $this->max) {
            throw new \InvalidArgumentException(sprintf(
                'Length min %d is greater than max %d. Swap them, for example #[Length(min: %d, max: %d)].',
                $this->min,
                $this->max,
                $this->max,
                $this->min,
            ));
        }
    }

    #[\Override]
    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        if (is_string($value)) {
            $count = mb_strlen($value);
            $unit = 'characters';
        } elseif (is_array($value)) {
            $count = count($value);
            $unit = 'items';
        } else {
            return 'Length applies to a string or a list';
        }

        $min = $this->min;
        $max = $this->max;
        if ($min !== null && $max !== null && ($count < $min || $count > $max)) {
            return 'Length must be between ' . $min . ' and ' . $max . ' ' . $unit;
        }
        if ($min !== null && $count < $min) {
            return 'Length must be at least ' . $min . ' ' . $unit;
        }
        if ($max !== null && $count > $max) {
            return 'Length must be at most ' . $max . ' ' . $unit;
        }

        return null;
    }
}
