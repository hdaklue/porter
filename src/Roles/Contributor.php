<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Roles;

final class Contributor extends BaseRole
{
    public function getName(): string
    {
        return 'contributor';
    }

    public function getLevel(): int
    {
        return 3;
    }
}