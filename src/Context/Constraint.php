<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Context;

use Hdaklue\LaraRbac\Context\Rules\BaseRule;

/**
 * Constraint - Context validation rule.
 */
final class Constraint
{
    public function __construct(
        public readonly string $field,
        public readonly BaseRule $rule,
        public readonly mixed $value = null
    ) {}

    /**
     * Create new constraint.
     */
    public static function make(string $field, BaseRule $rule, mixed $value = null): self
    {
        return new self($field, $rule, $value);
    }

    /**
     * Validate constraint against context.
     */
    public function validate(array $context): bool
    {
        $contextValue = ContextHelper::getValue($context, $this->field);
        return $this->rule->validate($contextValue, $this->value);
    }

    /**
     * Check if constraint is valid.
     */
    public function isValid(): bool
    {
        if (trim($this->field) === '') {
            return false;
        }

        if ($this->rule->requiresValue()) {
            return $this->rule->isValidRuleValue($this->value);
        }

        return true;
    }

    /**
     * Convert to array rule format.
     */
    public function toArray(): array
    {
        $rule = [
            'field' => $this->field,
            'operator' => $this->getOperatorString(),
        ];

        if ($this->rule->requiresValue()) {
            $rule['value'] = $this->value;
        }

        return $rule;
    }

    /**
     * Get operator string from rule class.
     */
    private function getOperatorString(): string
    {
        return match($this->rule::class) {
            \Hdaklue\LaraRbac\Context\Rules\Equals::class => '===',
            \Hdaklue\LaraRbac\Context\Rules\NotEquals::class => '!==',
            \Hdaklue\LaraRbac\Context\Rules\GreaterThan::class => '>',
            \Hdaklue\LaraRbac\Context\Rules\LessThan::class => '<',
            \Hdaklue\LaraRbac\Context\Rules\LessThanOrEqual::class => '<=',
            \Hdaklue\LaraRbac\Context\Rules\Between::class => 'between',
            \Hdaklue\LaraRbac\Context\Rules\Contains::class => 'contains',
            \Hdaklue\LaraRbac\Context\Rules\In::class => 'in',
            \Hdaklue\LaraRbac\Context\Rules\IsNull::class => 'is_null',
            \Hdaklue\LaraRbac\Context\Rules\IsNotNull::class => 'is_not_null',
            default => 'unknown',
        };
    }

    /**
     * Create from array data.
     */
    public static function fromArray(array $data): self
    {
        $field = $data['field'] ?? '';
        $operatorString = $data['operator'] ?? '';
        $rule = self::createRuleFromString($operatorString);
        $value = $data['value'] ?? null;

        return new self($field, $rule, $value);
    }

    /**
     * Create rule instance from operator string.
     */
    private static function createRuleFromString(string $operator): BaseRule
    {
        return match($operator) {
            '===' => new \Hdaklue\LaraRbac\Context\Rules\Equals(),
            '!==' => new \Hdaklue\LaraRbac\Context\Rules\NotEquals(),
            '>' => new \Hdaklue\LaraRbac\Context\Rules\GreaterThan(),
            '<' => new \Hdaklue\LaraRbac\Context\Rules\LessThan(),
            '<=' => new \Hdaklue\LaraRbac\Context\Rules\LessThanOrEqual(),
            'between' => new \Hdaklue\LaraRbac\Context\Rules\Between(),
            'contains' => new \Hdaklue\LaraRbac\Context\Rules\Contains(),
            'in' => new \Hdaklue\LaraRbac\Context\Rules\In(),
            'is_null' => new \Hdaklue\LaraRbac\Context\Rules\IsNull(),
            'is_not_null' => new \Hdaklue\LaraRbac\Context\Rules\IsNotNull(),
            default => throw new \InvalidArgumentException("Unknown operator: {$operator}"),
        };
    }
}