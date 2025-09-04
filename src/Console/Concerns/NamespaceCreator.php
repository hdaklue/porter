<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Console\Concerns;

trait NamespaceCreator
{
    /**
     * Get the root namespace for Porter roles
     */
    private function rootNamespace(): string
    {
        return config('porter.namespace', 'App\\Porter');
    }

    /**
     * Get the Porter directory path using GeneratorCommand pattern
     */
    private function getPorterDirectory(): string
    {
        $namespace = $this->rootNamespace();
        $fullPath = str_replace('\\', '/', $namespace);
        
        return base_path(lcfirst($fullPath));
    }
}