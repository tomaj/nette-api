<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Validation;

/**
 * Inclusive numeric bounds for an int or float property. Either bound may be omitted.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final readonly class Range implements ConstraintInterface
{
    public function __construct(
        public int|float|null $min = null,
        public int|float|null $max = null,
    ) {
        if ($this->min === null && $this->max === null) {
            throw new \InvalidArgumentException('Range needs min, max or both, for example #[Range(min: 1, max: 100)].');
        }
        if ($this->min !== null && $this->max !== null && $this->min > $this->max) {
            throw new \InvalidArgumentException(sprintf(
                'Range min %s is greater than max %s. Swap them, for example #[Range(min: %s, max: %s)].',
                $this->number($this->min),
                $this->number($this->max),
                $this->number($this->max),
                $this->number($this->min),
            ));
        }
    }

    #[\Override]
    public function validate(mixed $value, ConstraintContext $context): ?string
    {
        if (!is_int($value) && !is_float($value)) {
            return 'Range applies to an integer or a float';
        }

        $min = $this->min;
        $max = $this->max;
        if ($min !== null && $max !== null && ($value < $min || $value > $max)) {
            return 'Range must be between ' . $this->number($min) . ' and ' . $this->number($max);
        }
        if ($min !== null && $value < $min) {
            return 'Range must be greater than or equal to ' . $this->number($min);
        }
        if ($max !== null && $value > $max) {
            return 'Range must be less than or equal to ' . $this->number($max);
        }

        return null;
    }

    private function number(int|float $value): string
    {
        $text = rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');

        return $text === '' ? '0' : $text;
    }
}
