<?php

namespace Tomaj\NetteApi\Params;

use Nette\Application\UI\Form;

interface ParamInterface
{
    public function isValid(): bool;

    public function getErrors(): array;

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
