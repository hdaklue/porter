<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Context\Rules;

/**
 * LessThanOrEqual Rule - Numeric less than or equal validation.
 */
final class LessThanOrEqual extends BaseRule
{
    public function validate(mixed $contextValue, mixed $ruleValue): bool
    {
        return is_numeric($contextValue) && is_numeric($ruleValue) && $contextValue <= $ruleValue;
    }

    public function isValidRuleValue(mixed $value): bool
    {
        return is_numeric($value);
    }
}