<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Roles;

final class Editor extends BaseRole
{
    public function getName(): string
    {
        return 'editor';
    }

    public function getLevel(): int
    {
        return 4;
    }
}
