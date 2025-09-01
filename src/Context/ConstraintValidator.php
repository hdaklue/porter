<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Context;

/**
 * Constraint Validator - Validates multiple constraints.
 */
final class ConstraintValidator
{
    /**
     * Validate all constraints against context.
     */
    public static function validate(array $constraints, array $context): bool
    {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Constraint && !$constraint->validate($context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate constraints from array rules.
     */
    public static function validateRules(array $rules, array $context): bool
    {
        foreach ($rules as $rule) {
            try {
                $constraint = Constraint::fromArray($rule);
                if (!$constraint->validate($context)) {
                    return false;
                }
            } catch (\Exception) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate constraint structures.
     */
    public static function validateStructures(array $constraints): array
    {
        $errors = [];

        foreach ($constraints as $index => $constraint) {
            if (!($constraint instanceof Constraint)) {
                $errors[] = "Constraint {$index}: Must be instance of Constraint";
                continue;
            }

            if (!$constraint->isValid()) {
                $errors[] = "Constraint {$index}: Invalid constraint structure";
            }
        }

        return $errors;
    }
}