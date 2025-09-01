<?php

declare(strict_types=1);

use Hdaklue\MargRbac\Enums\Role\RoleEnum;
use Hdaklue\MargRbac\Events\Role\EntityAllRolesRemoved;
use Hdaklue\MargRbac\Events\Role\EntityRoleAssigned;
use Hdaklue\MargRbac\Events\Role\EntityRoleRemoved;
use Hdaklue\MargRbac\Models\Role;
use Hdaklue\MargRbac\Models\Tenant;
use Hdaklue\MargRbac\Models\User;
use Hdaklue\MargRbac\Services\Role\RoleAssignmentService;
use Illuminate\Support\Facades\Event;

describe('RoleAssignmentService Events', function () {
    beforeEach(function () {
        $this->service = app(RoleAssignmentService::class);
        
        // Create test user
        $this->user = User::factory()->create();
        
        // Create test tenant with system roles
        $this->tenant = Tenant::factory()->create();
        
        // Create system roles for the tenant
        collect(RoleEnum::cases())->each(function ($case) {
            $this->tenant->systemRoles()->create(['name' => $case->value]);
        });
    });

    it('dispatches EntityRoleAssigned event on new assignment', function () {
        Event::fake();

        $this->service->assign($this->user, $this->tenant, RoleEnum::ADMIN);

        Event::assertDispatched(EntityRoleAssigned::class, function ($event) {
            return $event->user->is($this->user) &&
                   $event->entity->is($this->tenant) &&
                   $event->role === RoleEnum::ADMIN;
        });
    });

    it('does not dispatch event for duplicate assignment', function () {
        Event::fake();

        // First assignment should dispatch event
        $this->service->assign($this->user, $this->tenant, RoleEnum::ADMIN);
        Event::assertDispatched(EntityRoleAssigned::class);

        // Reset event fake to test second assignment
        Event::fake();

        // Duplicate assignment should not dispatch event
        $this->service->assign($this->user, $this->tenant, RoleEnum::ADMIN);
        Event::assertNotDispatched(EntityRoleAssigned::class);
    });

    it('dispatches EntityRoleRemoved event on single role removal', function () {
        Event::fake();

        // First assign a role
        $this->service->assign($this->user, $this->tenant, RoleEnum::ADMIN);

        // Reset event fake to focus on removal
        Event::fake();

        // Remove the role
        $this->service->remove($this->user, $this->tenant);

        Event::assertDispatched(EntityRoleRemoved::class, function ($event) {
            return $event->user->is($this->user) &&
                   $event->entity->is($this->tenant) &&
                   $event->role === RoleEnum::ADMIN->value;
        });
    });

    it('dispatches both removal and assignment events on role change', function () {
        Event::fake();

        // First assign a role
        $this->service->assign($this->user, $this->tenant, RoleEnum::ADMIN);

        // Reset event fake to focus on role change
        Event::fake();

        // Change the role
        $this->service->changeRoleOn($this->user, $this->tenant, RoleEnum::EDITOR);

        // Should dispatch both removed (old role) and assigned (new role) events
        Event::assertDispatched(EntityRoleRemoved::class, function ($event) {
            return $event->user->is($this->user) &&
                   $event->entity->is($this->tenant) &&
                   $event->role === RoleEnum::ADMIN->value;
        });

        Event::assertDispatched(EntityRoleAssigned::class, function ($event) {
            return $event->user->is($this->user) &&
                   $event->entity->is($this->tenant) &&
                   $event->role === RoleEnum::EDITOR;
        });
    });

    it('does not dispatch events when no roles to remove', function () {
        Event::fake();

        // Try to remove roles when user has none
        $this->service->remove($this->user, $this->tenant);

        // No events should be dispatched
        Event::assertNotDispatched(EntityRoleRemoved::class);
        Event::assertNotDispatched(EntityAllRolesRemoved::class);
    });
});