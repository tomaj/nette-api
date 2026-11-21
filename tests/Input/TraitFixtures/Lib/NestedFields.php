<?php

declare(strict_types=1);

namespace Tomaj\NetteApi\Test\Input\TraitFixtures\Lib;

// no import of LineItem here: the constructor comes from OrderFields, whose file imports it
trait NestedFields
{
    use OrderFields;
}
