<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Contracts;

interface RoleContract
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
    public function isHigherThan(RoleContract $other): bool;

    /**
     * Check if this role is lower than another role.
     */
    public function isLowerThan(RoleContract $other): bool;

    /**
     * Check if this role is lower than or equal to another role.
     */
    public function isLowerThanOrEqual(RoleContract $other): bool;

    /**
     * Check if this role is equal to another role.
     */
    public function isEqualTo(RoleContract $other): bool;

    /**
     * Check if this role is higher than or equal to another role.
     */
    public function isHigherThanOrEqual(RoleContract $other): bool;

    /**
     * Check if this role is at least the same level as another.
     */
    public function isAtLeast(RoleContract $other): bool;

    /**
     * Create role instance from name.
     */
    public static function make(string $name): static;

    /**
     * Get all available role instances.
     */
    public static function all(): array;

    /**
     * Get the database key for this role.
     * Returns encrypted/hashed version of role name for secure storage.
     */
    public static function getDbKey(): string;
}
