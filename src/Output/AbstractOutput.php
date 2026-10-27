<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output;

abstract class AbstractOutput implements OutputInterface
{
    /** @var array<string, mixed> */
    protected array $examples = [];

    public function __construct(protected int $code, protected string $description = '')
    {
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $name Example name
     * @param mixed $example Example
     */
    public function addExample(string $name, $example): self
    {
        $this->examples[$name] = $example;
        return $this;
    }

    /**
     * Returns first example
     * @return mixed
     */
    public function getExample()
    {
        if (empty($this->examples)) {
            return null;
        }

        return reset($this->examples);
    }

    /**
     * Returns all examples
     * @return array<string, mixed>
     */
    public function getExamples(): array
    {
        return $this->examples;
    }
}
