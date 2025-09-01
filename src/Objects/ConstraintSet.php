<?php

declare(strict_types=1);

namespace Hdaklue\LaraRbac\Objects;

use Hdaklue\LaraRbac\Context\Constraint;
use Illuminate\Support\Str;

/**
 * ConstraintSet - Named constraint set with validation rules.
 */
final class ConstraintSet
{
    public function __construct(
        public string $name,
        public string $key,
        public string $description = '',
        public array $constraints = []
    ) {}

    /**
     * Create constraint set with name (key auto-generated as snake_case).
     */
    public static function make(string $name, string $description = ''): self
    {
        $key = Str::snake($name);
        return new self($name, $key, $description);
    }

    /**
     * Set description.
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Add constraint.
     */
    public function constrain(Constraint $constraint): self
    {
        $this->constraints[] = $constraint;
        return $this;
    }

    /**
     * Add multiple constraints.
     */
    public function constraints(array $constraints): self
    {
        foreach ($constraints as $constraint) {
            if ($constraint instanceof Constraint) {
                $this->constraints[] = $constraint;
            }
        }
        return $this;
    }

    /**
     * Resolve constraint set from key.
     */
    public static function resolve(string $key): ?self
    {
        // Try to find JSON file
        try {
            $filePath = config_path("constraints/{$key}.json");
        } catch (\Throwable) {
            $filePath = __DIR__ . "/../../constraints/{$key}.json";
        }
        
        if (!file_exists($filePath)) {
            return null;
        }

        $data = json_decode(file_get_contents($filePath), true);
        if (!$data) {
            return null;
        }

        // Create constraint set from data
        $constraintSet = new self(
            name: $data['name'] ?? $key,
            key: $key,
            description: $data['description'] ?? ''
        );

        // Load constraints if present
        if (isset($data['context_rules']) && is_array($data['context_rules'])) {
            foreach ($data['context_rules'] as $rule) {
                try {
                    $constraintSet->constraints[] = Constraint::fromArray($rule);
                } catch (\Exception) {
                    // Skip invalid constraints
                }
            }
        }

        return $constraintSet;
    }

    /**
     * Check if context passes all constraints.
     */
    public function allows($user, array $context = []): bool
    {
        foreach ($this->constraints as $constraint) {
            if (!$constraint->validate($context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Save constraint set to JSON file.
     */
    public function save(string $basePath = null): void
    {
        // Use provided path or try config_path, fallback to local path
        if ($basePath) {
            $filePath = $basePath . "/{$this->key}.json";
        } else {
            try {
                // Try to use Laravel's config_path if available
                $filePath = config_path("constraints/{$this->key}.json");
            } catch (\Throwable) {
                // Fall back to package constraints directory
                $filePath = __DIR__ . "/../../constraints/{$this->key}.json";
            }
        }
        
        $dir = dirname($filePath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'context_rules' => array_map(fn($constraint) => $constraint->toArray(), $this->constraints)
        ];

        file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT));
    }
}