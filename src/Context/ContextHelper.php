<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Context;

use Illuminate\Support\Str;

/**
 * ContextHelper - Utility for working with context data.
 */
final class ContextHelper
{
    /**
     * Get context value using dot notation.
     */
    public static function getValue(array $context, string $field): mixed
    {
        if (!Str::contains($field, '.')) {
            return $context[$field] ?? null;
        }

        $keys = explode('.', $field);
        $value = $context;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } elseif (is_object($value) && isset($value->{$key})) {
                $value = $value->{$key};
            } else {
                return null;
            }
        }

        return $value;
    }
}