<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Context\Rules;

/**
 * Equals Rule - Exact equality validation.
 */
final class Equals extends BaseRule
{
    public function validate(mixed $contextValue, mixed $ruleValue): bool
    {
        return $contextValue === $ruleValue;
    }

    public function isValidRuleValue(mixed $value): bool
    {
        return true;
    }
}