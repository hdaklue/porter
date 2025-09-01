<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Context\Rules;

/**
 * BaseRule - Abstract base class for validation rules.
 */
abstract class BaseRule
{
    /**
     * Validate context value against rule value.
     */
    abstract public function validate(mixed $contextValue, mixed $ruleValue): bool;

    /**
     * Check if rule value is valid format.
     */
    abstract public function isValidRuleValue(mixed $value): bool;

    /**
     * Check if rule requires a value.
     */
    public function requiresValue(): bool
    {
        return true;
    }
}