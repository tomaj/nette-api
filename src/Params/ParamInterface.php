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

    public function getAvailableValues(): ?array;

    public function isMulti(): bool;

    public function getDescription(): string;

    /**
     * default value
     * @return mixed
     */
    public function getDefault();

    /**
     * example value
     * @return mixed
     */
    public function getExample();

    public function getValue();

    public function updateConsoleForm(Form $form): void;
}
