<?php

namespace Tomaj\NetteApi;

interface EndpointInterface
{
    public function getMethod();

    public function getVersion();

    public function getPackage();

    public function getApiAction();

    public function getUrl();
}
