<?php

return [
    'admin' => [
        'label' => 'Administrator',
        'description' => 'Full system access with all administrative privileges',
    ],
    'manager' => [
        'label' => 'Manager',
        'description' => 'Manages teams and resources with elevated permissions',
    ],
    'editor' => [
        'label' => 'Editor',
        'description' => 'Creates and modifies content with publishing capabilities',
    ],
    'contributor' => [
        'label' => 'Contributor',
        'description' => 'Contributes content and collaborates on projects',
    ],
    'viewer' => [
        'label' => 'Viewer',
        'description' => 'Read-only access to view content and resources',
    ],
    'guest' => [
        'label' => 'Guest',
        'description' => 'Limited access for temporary or anonymous users',
    ],
];
