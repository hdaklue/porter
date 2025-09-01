<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Context\Rules;

/**
 * NotEquals Rule - Inequality validation.
 */
final class NotEquals extends BaseRule
{
    public function validate(mixed $contextValue, mixed $ruleValue): bool
    {
        return $contextValue !== $ruleValue;
    }

    public function isValidRuleValue(mixed $value): bool
    {
        return true;
    }
}