<?php

declare(strict_types=1);

namespace App\Api\Input;

enum UserStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
}
