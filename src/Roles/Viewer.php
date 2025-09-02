<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Roles;

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