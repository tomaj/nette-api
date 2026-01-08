<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Params;

use Nette\Application\UI\Form;
use Tomaj\NetteApi\ValidationResult\ValidationResultInterface;

interface ParamInterface
{
    public function validate(): ValidationResultInterface;

    public function getType(): string;

    public function getKey(): string;

    public function isRequired(): bool;

    /**
     * @return array<mixed>|null
     */
    public function getAvailableValues(): ?array;

    public function isMulti(): bool;

    public function getDescription(): string;

    public function getDefault(): mixed;

    public function getExample(): mixed;

    public function getValue(): mixed;

    public function updateConsoleForm(Form $form): void;
}
