<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Feature;

use Hdaklue\Porter\RoleFactory;
use Hdaklue\Porter\Validators\RoleValidator;
use Illuminate\Support\Facades\File;
use Hdaklue\Porter\Tests\TestCase;

class RoleFactoryUsageSeparateProcessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RoleValidator::clearCache();
        
        // Create temporary porter directory
        $this->tempDir = sys_get_temp_dir() . '/porter_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        
        // Create BaseRole.php (should be ignored)
        File::put($this->tempDir . '/BaseRole.php', '<?php
namespace App\Porter;
abstract class BaseRole {}
');
    }

    protected function tearDown(): void
    {
        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            File::deleteDirectory($this->tempDir);
        }
        
        RoleValidator::clearCache();
        parent::tearDown();
    }

    /**
     * @test
     * @runInSeparateProcess
     */
    public function it_demonstrates_the_new_dynamic_role_factory_usage(): void
    {
        // Arrange: Create some roles using the porter command
        $this->artisan('porter:create', [
            'name' => 'Admin',
            '--description' => 'Admin role',  // Simplified expectation
        ])
            ->expectsChoice('Select creation mode:', 'lowest', ['lowest', 'highest'])
            ->assertSuccessful();

        $this->artisan('porter:create', [
            'name' => 'ProjectManager',
            '--description' => 'ProjectManager role',  // Simplified expectation
        ])
            ->expectsChoice('Select creation mode:', 'highest', ['lowest', 'highest', 'lower', 'higher'])
            ->assertSuccessful();

        $this->artisan('porter:create', [
            'name' => 'TeamLead',
            '--description' => 'TeamLead role',  // Simplified expectation
        ])
            ->expectsChoice('Select creation mode:', 'lower', ['lowest', 'highest', 'lower', 'higher'])
            ->expectsChoice('Which role do you want to reference?', 'ProjectManager', ['Admin', 'ProjectManager'])
            ->assertSuccessful();

        // Act: Use the dynamic factory methods - these work like magic!
        $admin = RoleFactory::admin();                    // Creates Admin role
        $projectManager = RoleFactory::projectManager();  // Creates ProjectManager role
        $teamLead = RoleFactory::teamLead();             // Creates TeamLead role

        // Assert: All roles are properly instantiated
        $this->assertInstanceOf(\App\Porter\Admin::class, $admin);
        $this->assertInstanceOf(\App\Porter\ProjectManager::class, $projectManager);
        $this->assertInstanceOf(\App\Porter\TeamLead::class, $teamLead);

        // Assert: Role properties are correct
        $this->assertEquals('admin', $admin->getName());
        $this->assertEquals(1, $admin->getLevel());
        $this->assertEquals('Admin role', $admin->getDescription());

        $this->assertEquals('project_manager', $projectManager->getName());
        $this->assertEquals(3, $projectManager->getLevel());  // Pushed up by TeamLead creation
        $this->assertEquals('ProjectManager role', $projectManager->getDescription());

        $this->assertEquals('team_lead', $teamLead->getName());
        $this->assertEquals(2, $teamLead->getLevel());  // Takes ProjectManager's original level
        $this->assertEquals('TeamLead role', $teamLead->getDescription());

        // Act: Test role comparison methods
        $this->assertTrue($projectManager->isHigherThan($teamLead));
        $this->assertTrue($teamLead->isHigherThan($admin));
        $this->assertTrue($admin->isLowerThan($projectManager));

        // Act: Get all roles at once
        $allRoles = RoleFactory::allFromPorterDirectory();
        $this->assertCount(3, $allRoles);
        $this->assertArrayHasKey('Admin', $allRoles);
        $this->assertArrayHasKey('ProjectManager', $allRoles);
        $this->assertArrayHasKey('TeamLead', $allRoles);

        // Demonstrate usage with concern traits (type-safe!)
        // Note: In a real app, you'd use these roles with your entities like:
        // $user->assign($project, Porter::projectManager());
        // $user->hasAssignmentOn($project, Porter::admin());
    }

    /** @test */
    public function it_allows_configurable_namespace_and_directory(): void
    {
        // This test demonstrates that the factory reads from config
        $porterDir = config('porter.directory');
        $namespace = config('porter.namespace');

        $this->assertEquals(app_path('Porter'), $porterDir);  // Default config
        $this->assertEquals('App\\Porter', $namespace);        // Default config

        // The factory uses these config values internally
        $this->assertFalse(RoleFactory::existsInPorterDirectory('NonExistent'));
    }
}
