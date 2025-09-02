<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Fixtures;

use Hdaklue\Porter\Roles\BaseRole;

final class TestAdmin extends BaseRole
{
    public function getName(): string
    {
        return 'TestAdmin';
    }

    public function getLevel(): int
    {
        return 10;
    }

    public function getLabel(): string
    {
        return 'Test Administrator';
    }

    public function getDescription(): string
    {
        return 'Test role with full administrative privileges';
    }
}
