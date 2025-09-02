<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Fixtures;

use Hdaklue\Porter\Roles\BaseRole;

final class TestEditor extends BaseRole
{
    public function getName(): string
    {
        return 'TestEditor';
    }

    public function getLevel(): int
    {
        return 5;
    }

    public function getLabel(): string
    {
        return 'Test Editor';
    }

    public function getDescription(): string
    {
        return 'Test role with content editing privileges';
    }
}
