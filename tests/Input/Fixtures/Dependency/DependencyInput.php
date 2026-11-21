<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Dependency;

final readonly class DependencyInput extends DependencyParentInput
{
    use DependencyTrait;

    // a constant of a built-in class has no file to depend on
    private const string FORMAT = \DateTimeInterface::ATOM;

    // self:: resolves to no class and must not end the scan of the rest of the file
    private const string LABEL = self::FORMAT . DependencyLabels::SUFFIX;
}
