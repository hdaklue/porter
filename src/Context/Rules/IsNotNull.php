<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Context\Rules;

/**
 * IsNotNull Rule - Non-null value validation.
 */
final class IsNotNull extends BaseRule
{
    public function validate(mixed $contextValue, mixed $ruleValue): bool
    {
        return $contextValue !== null;
    }

    public function isValidRuleValue(mixed $value): bool
    {
        return true;
    }

    public function requiresValue(): bool
    {
        return false;
    }
}