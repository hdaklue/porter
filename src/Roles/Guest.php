<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Roles;

final class Guest extends BaseRole
{
    public function getName(): string
    {
        return 'guest';
    }

    public function getLevel(): int
    {
        return 1;
    }
}
