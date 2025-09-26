<?php

declare(strict_types=1);

use Hdaklue\Porter\RoleManager;
use Hdaklue\Porter\Rules\AssignedTo;
use Hdaklue\Porter\Rules\NotAssignedTo;
use Hdaklue\Porter\Tests\Fixtures\TestAdmin;
use Hdaklue\Porter\Tests\Fixtures\TestEditor;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    // Create test users table
    if (! Schema::hasTable('test_users')) {
        Schema::create('test_users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('current_tenant_id')->nullable();
            $table->timestamps();
        });
    }

    // Create test projects table
    if (! Schema::hasTable('test_projects')) {
        Schema::create('test_projects', function ($table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('tenant_id')->nullable();
            $table->timestamps();
        });
    }

    // Create test entities
    $this->user = TestUser::create([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    $this->anotherUser = TestUser::create([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
    ]);

    $this->project = TestProject::create([
        'name' => 'Test Project',
        'description' => 'A test project',
    ]);

    $this->anotherProject = TestProject::create([
        'name' => 'Another Project',
        'description' => 'Another test project',
    ]);

    $this->roleManager = app(RoleManager::class);
});

describe('NotAssignedTo Rule', function () {
    test('passes validation when user is not assigned to entity', function () {
        $rule = new NotAssignedTo($this->user, $this->project);
        $failed = false;

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    });

    test('fails validation when user is assigned to entity', function () {
        // Assign user to project
        $this->roleManager->assign($this->user, $this->project, new TestAdmin());

        $rule = new NotAssignedTo($this->user, $this->project);
        $failed = false;
        $failMessage = '';

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = $message;
        });

        expect($failed)->toBeTrue();
        expect($failMessage)->toBe('The :attribute is already assigned to this entity.');
    });

    test('passes validation when user is assigned to different entity', function () {
        // Assign user to a different project
        $this->roleManager->assign($this->user, $this->anotherProject, new TestAdmin());

        $rule = new NotAssignedTo($this->user, $this->project);
        $failed = false;

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    });

    test('passes validation when different user is assigned to entity', function () {
        // Assign different user to project
        $this->roleManager->assign($this->anotherUser, $this->project, new TestEditor());

        $rule = new NotAssignedTo($this->user, $this->project);
        $failed = false;

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    });

    test('fails validation when user has multiple roles on entity', function () {
        // Assign user with admin role
        $this->roleManager->assign($this->user, $this->project, new TestAdmin());

        // Change to editor role (still assigned)
        $this->roleManager->changeRoleOn($this->user, $this->project, new TestEditor());

        $rule = new NotAssignedTo($this->user, $this->project);
        $failed = false;

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeTrue();
    });

    test('passes validation after role is removed', function () {
        // Assign and then remove role
        $this->roleManager->assign($this->user, $this->project, new TestAdmin());
        $this->roleManager->remove($this->user, $this->project);

        $rule = new NotAssignedTo($this->user, $this->project);
        $failed = false;

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    });

    test('works with different attribute names', function () {
        $this->roleManager->assign($this->user, $this->project, new TestAdmin());

        $rule = new NotAssignedTo($this->user, $this->project);
        $failed = false;
        $failMessage = '';

        $rule->validate('assignable_id', $this->user->id, function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = $message;
        });

        expect($failed)->toBeTrue();
        expect($failMessage)->toBe('The :attribute is already assigned to this entity.');
    });
});

describe('AssignedTo Rule', function () {
    test('fails validation when user is not assigned to entity', function () {
        $rule = new AssignedTo($this->user, $this->project);
        $failed = false;
        $failMessage = '';

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = $message;
        });

        expect($failed)->toBeTrue();
        expect($failMessage)->toBe('The :attribute is not assigned to this entity.');
    });

    test('passes validation when user is assigned to entity', function () {
        // Assign user to project
        $this->roleManager->assign($this->user, $this->project, new TestAdmin());

        $rule = new AssignedTo($this->user, $this->project);
        $failed = false;

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    });

    test('fails validation when user is assigned to different entity', function () {
        // Assign user to a different project
        $this->roleManager->assign($this->user, $this->anotherProject, new TestAdmin());

        $rule = new AssignedTo($this->user, $this->project);
        $failed = false;
        $failMessage = '';

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = $message;
        });

        expect($failed)->toBeTrue();
        expect($failMessage)->toBe('The :attribute is not assigned to this entity.');
    });

    test('fails validation when different user is assigned to entity', function () {
        // Assign different user to project
        $this->roleManager->assign($this->anotherUser, $this->project, new TestEditor());

        $rule = new AssignedTo($this->user, $this->project);
        $failed = false;
        $failMessage = '';

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = $message;
        });

        expect($failed)->toBeTrue();
        expect($failMessage)->toBe('The :attribute is not assigned to this entity.');
    });

    test('passes validation when user has any role on entity', function () {
        // Test with different roles
        $roles = [new TestAdmin(), new TestEditor()];

        foreach ($roles as $role) {
            // Clean slate
            $this->roleManager->remove($this->user, $this->project);

            // Assign role
            $this->roleManager->assign($this->user, $this->project, $role);

            $rule = new AssignedTo($this->user, $this->project);
            $failed = false;

            $rule->validate('user_id', $this->user->id, function ($message) use (&$failed) {
                $failed = true;
            });

            expect($failed)->toBeFalse();
        }
    });

    test('passes validation when role is changed but user still assigned', function () {
        // Assign user with admin role
        $this->roleManager->assign($this->user, $this->project, new TestAdmin());

        // Change to editor role (still assigned)
        $this->roleManager->changeRoleOn($this->user, $this->project, new TestEditor());

        $rule = new AssignedTo($this->user, $this->project);
        $failed = false;

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeFalse();
    });

    test('fails validation after role is removed', function () {
        // Assign and then remove role
        $this->roleManager->assign($this->user, $this->project, new TestAdmin());
        $this->roleManager->remove($this->user, $this->project);

        $rule = new AssignedTo($this->user, $this->project);
        $failed = false;
        $failMessage = '';

        $rule->validate('user_id', $this->user->id, function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = $message;
        });

        expect($failed)->toBeTrue();
        expect($failMessage)->toBe('The :attribute is not assigned to this entity.');
    });

    test('works with different attribute names', function () {
        $rule = new AssignedTo($this->user, $this->project);
        $failed = false;
        $failMessage = '';

        $rule->validate('assignable_id', $this->user->id, function ($message) use (&$failed, &$failMessage) {
            $failed = true;
            $failMessage = $message;
        });

        expect($failed)->toBeTrue();
        expect($failMessage)->toBe('The :attribute is not assigned to this entity.');
    });
});

describe('Edge Cases and Integration', function () {
    test('both rules work correctly in sequence', function () {
        // Initially user is not assigned - NotAssignedTo should pass, AssignedTo should fail
        $notAssignedRule = new NotAssignedTo($this->user, $this->project);
        $assignedRule = new AssignedTo($this->user, $this->project);

        $notAssignedFailed = false;
        $assignedFailed = false;

        $notAssignedRule->validate('user_id', $this->user->id, function ($message) use (&$notAssignedFailed) {
            $notAssignedFailed = true;
        });

        $assignedRule->validate('user_id', $this->user->id, function ($message) use (&$assignedFailed) {
            $assignedFailed = true;
        });

        expect($notAssignedFailed)->toBeFalse(); // Should pass
        expect($assignedFailed)->toBeTrue();     // Should fail

        // Assign user - NotAssignedTo should fail, AssignedTo should pass
        $this->roleManager->assign($this->user, $this->project, new TestAdmin());

        $notAssignedFailed = false;
        $assignedFailed = false;

        $notAssignedRule->validate('user_id', $this->user->id, function ($message) use (&$notAssignedFailed) {
            $notAssignedFailed = true;
        });

        $assignedRule->validate('user_id', $this->user->id, function ($message) use (&$assignedFailed) {
            $assignedFailed = true;
        });

        expect($notAssignedFailed)->toBeTrue();  // Should fail
        expect($assignedFailed)->toBeFalse();    // Should pass
    });

    test('rules handle null or invalid values gracefully', function () {
        $notAssignedRule = new NotAssignedTo($this->user, $this->project);
        $assignedRule = new AssignedTo($this->user, $this->project);

        $failed = false;

        // Test with null value
        $notAssignedRule->validate('user_id', null, function ($message) use (&$failed) {
            $failed = true;
        });

        // Should not throw exception, should handle gracefully
        expect($failed)->toBeFalse(); // No assignment with null, so NotAssignedTo passes

        $failed = false;
        $assignedRule->validate('user_id', null, function ($message) use (&$failed) {
            $failed = true;
        });

        expect($failed)->toBeTrue(); // No assignment with null, so AssignedTo fails
    });

    test('rules work with multiple entities of same type', function () {
        $project1 = $this->project;
        $project2 = $this->anotherProject;

        // Assign user to project1 only
        $this->roleManager->assign($this->user, $project1, new TestAdmin());

        // Test NotAssignedTo with project1 (should fail)
        $rule1 = new NotAssignedTo($this->user, $project1);
        $failed1 = false;
        $rule1->validate('user_id', $this->user->id, function ($message) use (&$failed1) {
            $failed1 = true;
        });
        expect($failed1)->toBeTrue();

        // Test NotAssignedTo with project2 (should pass)
        $rule2 = new NotAssignedTo($this->user, $project2);
        $failed2 = false;
        $rule2->validate('user_id', $this->user->id, function ($message) use (&$failed2) {
            $failed2 = true;
        });
        expect($failed2)->toBeFalse();

        // Test AssignedTo with project1 (should pass)
        $rule3 = new AssignedTo($this->user, $project1);
        $failed3 = false;
        $rule3->validate('user_id', $this->user->id, function ($message) use (&$failed3) {
            $failed3 = true;
        });
        expect($failed3)->toBeFalse();

        // Test AssignedTo with project2 (should fail)
        $rule4 = new AssignedTo($this->user, $project2);
        $failed4 = false;
        $rule4->validate('user_id', $this->user->id, function ($message) use (&$failed4) {
            $failed4 = true;
        });
        expect($failed4)->toBeTrue();
    });
});
