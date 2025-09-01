<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Context\Rules;

/**
 * In Rule - Array membership validation.
 */
final class In extends BaseRule
{
    public function validate(mixed $contextValue, mixed $ruleValue): bool
    {
        return is_array($ruleValue) && in_array($contextValue, $ruleValue, true);
    }

    public function isValidRuleValue(mixed $value): bool
    {
        return is_array($value) && !empty($value);
    }
}