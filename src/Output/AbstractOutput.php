<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output;

abstract class AbstractOutput implements OutputInterface
{
    protected $code;

    protected $description;

    public function __construct(int $code, string $description = '')
    {
        $this->code = $code;
        $this->description = $description;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getDescription(): string
    {
        return $this->description;
    }
}
