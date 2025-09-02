<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Concerns;

use Hdaklue\Porter\Contracts\RoleInterface;

trait HasRoleHierarchy
{
    /**
     * Check if this role is higher than another role.
     */
    public function isHigherThan(RoleInterface $other): bool
    {
        return $this->getLevel() > $other->getLevel();
    }

    /**
     * Check if this role is lower than another role.
     */
    public function isLowerThan(RoleInterface $other): bool
    {
        return $this->getLevel() < $other->getLevel();
    }

    /**
     * Check if this role is lower than or equal to another role.
     */
    public function isLowerThanOrEqual(RoleInterface $other): bool
    {
        return $this->getLevel() <= $other->getLevel();
    }

    /**
     * Check if this role is equal to another role.
     */
    public function isEqualTo(RoleInterface $other): bool
    {
        return $this->getLevel() === $other->getLevel();
    }

    /**
     * Check if this role is at least the same level as another.
     */
    public function isAtLeast(RoleInterface $other): bool
    {
        return $this->getLevel() >= $other->getLevel();
    }
}
