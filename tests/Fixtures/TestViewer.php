<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Fixtures;

use Hdaklue\Porter\Roles\BaseRole;

final class TestViewer extends BaseRole
{
    public function getName(): string
    {
        return 'TestViewer';
    }

    public function getLevel(): int
    {
        return 1;
    }

    public function getLabel(): string
    {
        return 'Test Viewer';
    }

    public function getDescription(): string
    {
        return 'Test role with read-only privileges';
    }
}
