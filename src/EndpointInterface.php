<?php

namespace Tomaj\NetteApi;

interface EndpointInterface
{
    public function getMethod(): string;

    public function getVersion(): int;

    public function getPackage(): string;

    public function getApiAction(): ?string;

    public function getUrl(): string;

    public function equals(EndpointInterface $endpoint): bool;
}
