<?php

declare(strict_types=1);

namespace PHPTypeS\Bridge\Tests\Fixtures\DTOs;

enum UserStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Banned   = 'banned';
}
