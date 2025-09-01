<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Context\Rules;

/**
 * Between Rule - Range validation.
 */
final class Between extends BaseRule
{
    public function validate(mixed $contextValue, mixed $ruleValue): bool
    {
        if (!is_numeric($contextValue) || !is_array($ruleValue) || count($ruleValue) !== 2) {
            return false;
        }

        [$min, $max] = $ruleValue;
        return is_numeric($min) && is_numeric($max) && $contextValue >= $min && $contextValue <= $max;
    }

    public function isValidRuleValue(mixed $value): bool
    {
        return is_array($value) && count($value) === 2 && is_numeric($value[0]) && is_numeric($value[1]);
    }
}