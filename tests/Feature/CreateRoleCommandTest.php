<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;



afterEach(function () {
    // Clean up created role files
    $porterDir = app_path('Porter');
    if (File::exists($porterDir)) {
        File::deleteDirectory($porterDir);
    }
});

test('it prevents creating role with duplicate level', function () {
    // Arrange: Create a role first
    $this->artisan('porter:create', [
        'name' => 'TestRole',
        '--level' => 5,
        '--description' => 'A test role',
    ])->assertSuccessful();

    // Act & Assert: Try to create another role with the same level
    $this->artisan('porter:create', [
        'name' => 'AnotherTestRole',
        '--level' => 5,
        '--description' => 'Another test role',
    ])
    ->expectsOutput('❌ Role level \'5\' is already used by role: TestRole')
    ->assertFailed();
});
