<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Output;

abstract class AbstractOutput implements OutputInterface
{
    protected $code;

    protected $description;

    /** @var array */
    protected $examples = [];

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

    /**
     * @param string $name Example name
     * @param mixed $example Example
     * @return Self
     */
    public function addExample(string $name, $example): self
    {
        $this->examples[$name] = $example;
        return $this;
    }

    /**
     * Set default example
     * @param mixed $example
     * @return self
     * @deprecated Use addExample instead
     */
    public function setExample($example): self
    {
        $this->examples["default"] = $example;
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
     * @return array
     */
    public function getExamples(): array
    {
        return $this->examples;
    }
}
