<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Roles;

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