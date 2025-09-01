<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Roles;

final class Viewer extends BaseRole
{
    public function getName(): string
    {
        return 'viewer';
    }

    public function getLevel(): int
    {
        return 2;
    }
}