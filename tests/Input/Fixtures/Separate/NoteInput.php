<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\Fixtures\Separate;

use Tomaj\NetteApi\Input\Body;

final readonly class NoteInput
{
    public function __construct(#[Body] public string $text)
    {
    }
}
