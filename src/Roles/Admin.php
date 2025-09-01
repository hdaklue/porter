<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Roles;

final class Admin extends BaseRole
{
    public function getName(): string
    {
        return 'admin';
    }

    public function getLevel(): int
    {
        return 6;
    }
}