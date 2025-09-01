<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Context\Rules;

use Illuminate\Support\Str;

/**
 * Contains Rule - String/array contains validation.
 */
final class Contains extends BaseRule
{
    public function validate(mixed $contextValue, mixed $ruleValue): bool
    {
        if (is_string($contextValue)) {
            return Str::contains($contextValue, (string) $ruleValue);
        }

        if (is_array($contextValue)) {
            return in_array($ruleValue, $contextValue, true);
        }

        return false;
    }

    public function isValidRuleValue(mixed $value): bool
    {
        return true;
    }
}