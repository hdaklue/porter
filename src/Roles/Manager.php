<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Roles;

final class Manager extends BaseRole
{
    public function getName(): string
    {
        return 'manager';
    }

    public function getLevel(): int
    {
        return 5;
    }
}