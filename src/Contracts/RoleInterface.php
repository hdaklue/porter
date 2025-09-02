<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Contracts;

interface RoleInterface
{
    /**
     * Get the role name.
     */
    public function getName(): string;

    /**
     * Get the hierarchical level of this role.
     * Higher numbers = more privileges.
     */
    public function getLevel(): int;

    /**
     * Get human-readable label for this role.
     */
    public function getLabel(): string;

    /**
     * Get description of role capabilities.
     */
    public function getDescription(): string;

    /**
     * Check if this role is higher than another role.
     */
    public function isHigherThan(RoleInterface $other): bool;

    /**
     * Check if this role is lower than another role.
     */
    public function isLowerThan(RoleInterface $other): bool;

    /**
     * Check if this role is lower than or equal to another role.
     */
    public function isLowerThanOrEqual(RoleInterface $other): bool;

    /**
     * Check if this role is equal to another role.
     */
    public function isEqualTo(RoleInterface $other): bool;

    /**
     * Check if this role is at least the same level as another.
     */
    public function isAtLeast(RoleInterface $other): bool;

    /**
     * Create role instance from name.
     */
    public static function make(string $name): static;

    /**
     * Get all available role instances.
     */
    public static function all(): array;
}