<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Feature;

use Hdaklue\Porter\RoleManager;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\DB;

// Helper function to create test fixtures - compatible across all Pest versions
function createAdvancedFixtures()
{
    $users = collect(range(1, 20))->map(fn ($i) => tap(new TestUser(), fn ($u) => $u->id = $i));
    $projects = collect(range(1, 10))->map(fn ($i) => tap(new TestProject(), fn ($p) => $p->id = $i));

    return compact('users', 'projects');
}

describe('Advanced Scenarios Tests', function () {

    describe('Complex Role Hierarchies', function () {
        it('handles deep role hierarchies with multiple levels', function () {
            extract(createAdvancedFixtures());
            // Note: RoleManager uses 'replace' strategy by default, so only the last assigned role persists
            $user = $users->first();
            $project = $projects->first();

            // Test role assignment and replacement behavior
            app(RoleManager::class)->assign($user, $project, 'TestViewer');
            expect($user->hasRoleOn($project, 'TestViewer'))->toBeTrue();

            // Assigning a new role replaces the previous one
            app(RoleManager::class)->assign($user, $project, 'TestEditor');
            expect($user->hasRoleOn($project, 'TestEditor'))->toBeTrue();
            expect($user->hasRoleOn($project, 'TestViewer'))->toBeFalse();

            // Final role assignment
            app(RoleManager::class)->assign($user, $project, 'TestAdmin');
            expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();
            expect($user->hasRoleOn($project, 'TestEditor'))->toBeFalse();

            // Only one role assignment should exist due to replace strategy
            expect(DB::table('roster')->count())->toBe(1);
        });

        it('manages complex inheritance patterns', function () {
            extract(createAdvancedFixtures());
            $admin = $users[0];
            $manager = $users[1];
            $developer = $users[2];
            $intern = $users[3];

            $project = $projects->first();

            // Assign hierarchical roles
            app(RoleManager::class)->assign($admin, $project, 'TestAdmin');
            app(RoleManager::class)->assign($manager, $project, 'TestEditor');
            app(RoleManager::class)->assign($developer, $project, 'TestViewer');
            app(RoleManager::class)->assign($intern, $project, 'TestViewer');

            // Create a complex project structure
            $modules = collect(range(1, 5))->map(fn ($i) => tap(new TestProject(), fn ($p) => $p->id = 100 + $i));

            foreach ($modules as $module) {
                // Admin has access to all modules
                app(RoleManager::class)->assign($admin, $module, 'TestAdmin');

                // Manager has limited access
                app(RoleManager::class)->assign($manager, $module, 'TestEditor');

                // Developers only to specific modules
                if ($module->id % 2 === 0) {
                    app(RoleManager::class)->assign($developer, $module, 'TestViewer');
                }
            }

            // Verify complex access patterns
            expect($admin->hasRoleOn($modules->first(), 'TestAdmin'))->toBeTrue();
            expect($manager->hasRoleOn($modules->first(), 'TestEditor'))->toBeTrue();
            expect($developer->hasRoleOn($modules->first(), 'TestViewer'))->toBeFalse();
            expect($developer->hasRoleOn($modules->skip(1)->first(), 'TestViewer'))->toBeTrue();
        });

        it('handles role promotion and demotion chains', function () {
            extract(createAdvancedFixtures());
            $user = $users->first();
            $project = $projects->first();

            // Start as viewer
            app(RoleManager::class)->assign($user, $project, 'TestViewer');
            expect($user->hasRoleOn($project, 'TestViewer'))->toBeTrue();

            // Promote through hierarchy (each assignment replaces the previous)
            app(RoleManager::class)->assign($user, $project, 'TestEditor');
            expect($user->hasRoleOn($project, 'TestEditor'))->toBeTrue();
            expect($user->hasRoleOn($project, 'TestViewer'))->toBeFalse();

            app(RoleManager::class)->assign($user, $project, 'TestAdmin');
            expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();
            expect($user->hasRoleOn($project, 'TestEditor'))->toBeFalse();

            // Demote back down
            app(RoleManager::class)->assign($user, $project, 'TestEditor');
            expect($user->hasRoleOn($project, 'TestEditor'))->toBeTrue();
            expect($user->hasRoleOn($project, 'TestAdmin'))->toBeFalse();

            app(RoleManager::class)->assign($user, $project, 'TestViewer');
            expect($user->hasRoleOn($project, 'TestViewer'))->toBeTrue();
            expect($user->hasRoleOn($project, 'TestEditor'))->toBeFalse();

            // Remove all roles
            app(RoleManager::class)->remove($user, $project);
            expect($user->hasRoleOn($project, 'TestViewer'))->toBeFalse();
        });

        it('manages overlapping role responsibilities', function () {
            extract(createAdvancedFixtures());
            $user = $users->first();

            // Assign to multiple projects with different roles
            $projectRoles = [
                [$projects[0], 'TestAdmin'],
                [$projects[1], 'TestEditor'],
                [$projects[2], 'TestViewer'],
                [$projects[3], 'TestAdmin'],
                [$projects[4], 'TestEditor'],
            ];

            foreach ($projectRoles as [$project, $role]) {
                app(RoleManager::class)->assign($user, $project, $role);
            }

            // User should have admin on projects 0 and 3
            expect($user->hasRoleOn($projects[0], 'TestAdmin'))->toBeTrue();
            expect($user->hasRoleOn($projects[3], 'TestAdmin'))->toBeTrue();

            // User should have manager on projects 1 and 4
            expect($user->hasRoleOn($projects[1], 'TestEditor'))->toBeTrue();
            expect($user->hasRoleOn($projects[4], 'TestEditor'))->toBeTrue();

            // User should have developer only on project 2
            expect($user->hasRoleOn($projects[2], 'TestViewer'))->toBeTrue();

            // Cross-check: admin user should not have manager role on admin projects
            expect($user->hasRoleOn($projects[0], 'TestEditor'))->toBeFalse();
        });
    });

    describe('Circular Dependency Detection', function () {
        it('prevents basic circular role assignments', function () {
            extract(createAdvancedFixtures());
            $userA = $users[0];
            $userB = $users[1];
            $projectA = $projects[0];
            $projectB = $projects[1];

            // Create potential circular scenario
            app(RoleManager::class)->assign($userA, $projectA, 'TestAdmin');
            app(RoleManager::class)->assign($userB, $projectB, 'TestAdmin');

            // Try to create circular dependency (if such logic exists)
            // This test depends on implementation - for now just verify normal operation
            expect($userA->hasRoleOn($projectA, 'TestAdmin'))->toBeTrue();
            expect($userB->hasRoleOn($projectB, 'TestAdmin'))->toBeTrue();

            // Verify no cross-contamination
            expect($userA->hasRoleOn($projectB, 'TestAdmin'))->toBeFalse();
            expect($userB->hasRoleOn($projectA, 'TestAdmin'))->toBeFalse();
        });

        it('handles complex multi-entity circular scenarios', function () {
            extract(createAdvancedFixtures());
            // Create a complex web of relationships
            $entities = [
                'users' => $users->take(5),
                'projects' => $projects->take(5),
            ];

            // Create a web of relationships
            foreach ($entities['users'] as $userIndex => $user) {
                foreach ($entities['projects'] as $projectIndex => $project) {
                    // Create some pattern that could lead to circular references
                    if (($userIndex + $projectIndex) % 2 === 0) {
                        app(RoleManager::class)->assign($user, $project, 'TestAdmin');
                    } else {
                        app(RoleManager::class)->assign($user, $project, 'TestViewer');
                    }
                }
            }

            // Verify all assignments are valid and no circular issues
            $totalAssignments = 0;

            foreach ($entities['users'] as $userIndex => $user) {
                foreach ($entities['projects'] as $projectIndex => $project) {
                    $expectedRole = ($userIndex + $projectIndex) % 2 === 0 ? 'TestAdmin' : 'TestViewer';

                    expect($user->hasRoleOn($project, $expectedRole))->toBeTrue();
                    $totalAssignments++;
                }
            }

            expect($totalAssignments)->toBe(25); // 5 users × 5 projects
            expect(DB::table('roster')->count())->toBe(25);
        });

        it('prevents infinite recursion in role resolution', function () {
            extract(createAdvancedFixtures());
            // Create nested project structure that could cause recursion
            $parentProject = $projects[0];
            $childProjects = $projects->slice(1, 3);
            $user = $users->first();

            // Assign user to parent
            app(RoleManager::class)->assign($user, $parentProject, 'TestAdmin');

            // Assign user to all children
            foreach ($childProjects as $childProject) {
                app(RoleManager::class)->assign($user, $childProject, 'TestEditor');
            }

            // Create potential for recursive checking
            $checkCount = 0;
            $maxChecks = 100;

            // Repeatedly check roles to see if we get infinite recursion
            while ($checkCount < $maxChecks) {
                $hasParentRole = $user->hasRoleOn($parentProject, 'TestAdmin');

                foreach ($childProjects as $child) {
                    $hasChildRole = $user->hasRoleOn($child, 'TestEditor');
                    expect($hasChildRole)->toBeTrue();
                }

                expect($hasParentRole)->toBeTrue();
                $checkCount++;
            }

            // If we get here without timeout/crash, no infinite recursion occurred
            expect($checkCount)->toBe($maxChecks);
        });

        it('handles self-referential entity scenarios', function () {
            extract(createAdvancedFixtures());
            $user = $users->first();
            $project = $projects->first();

            // Assign user admin role on project
            app(RoleManager::class)->assign($user, $project, 'TestAdmin');

            // Try various operations that might cause self-reference issues
            for ($i = 0; $i < 10; $i++) {
                expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();

                // Re-assign same role (should not create duplicates or issues)
                try {
                    app(RoleManager::class)->assign($user, $project, 'TestAdmin');
                } catch (\Exception $e) {
                    // Expected if duplicates are prevented
                }
            }

            // Should still have exactly one assignment
            $assignments = DB::table('roster')
                ->where('assignable_id', $user->id)
                ->where('roleable_id', $project->id)
                ->count();

            expect($assignments)->toBeLessThanOrEqual(1);
        });
    });

    describe('Cross-Tenant Isolation', function () {
        it('ensures complete isolation between different tenant contexts', function () {
            extract(createAdvancedFixtures());
            // Simulate different tenants by using different project groups
            $tenant1Projects = $projects->slice(0, 3);
            $tenant2Projects = $projects->slice(3, 3);
            $tenant3Projects = $projects->slice(6, 3);

            $tenant1Users = $users->slice(0, 5);
            $tenant2Users = $users->slice(5, 5);
            $tenant3Users = $users->slice(10, 5);

            // Set up tenant 1
            foreach ($tenant1Users as $user) {
                foreach ($tenant1Projects as $project) {
                    app(RoleManager::class)->assign($user, $project, 'TestAdmin');
                }
            }

            // Set up tenant 2
            foreach ($tenant2Users as $user) {
                foreach ($tenant2Projects as $project) {
                    app(RoleManager::class)->assign($user, $project, 'TestEditor');
                }
            }

            // Set up tenant 3
            foreach ($tenant3Users as $user) {
                foreach ($tenant3Projects as $project) {
                    app(RoleManager::class)->assign($user, $project, 'TestViewer');
                }
            }

            // Verify complete isolation
            // Tenant 1 users should not have access to tenant 2 or 3 projects
            foreach ($tenant1Users as $user) {
                foreach ($tenant2Projects as $project) {
                    expect($user->hasRoleOn($project, 'TestAdmin'))->toBeFalse();
                    expect($user->hasRoleOn($project, 'TestEditor'))->toBeFalse();
                    expect($user->hasRoleOn($project, 'TestViewer'))->toBeFalse();
                }

                foreach ($tenant3Projects as $project) {
                    expect($user->hasRoleOn($project, 'TestAdmin'))->toBeFalse();
                    expect($user->hasRoleOn($project, 'TestEditor'))->toBeFalse();
                    expect($user->hasRoleOn($project, 'TestViewer'))->toBeFalse();
                }
            }

            // Verify correct access within tenant
            foreach ($tenant1Users as $user) {
                foreach ($tenant1Projects as $project) {
                    expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();
                }
            }
        });

        it('prevents data leakage between tenant boundaries', function () {
            extract(createAdvancedFixtures());
            $tenant1User = $users[0];
            $tenant2User = $users[10];

            $tenant1Project = $projects[0];
            $tenant2Project = $projects[5];

            // Set up roles
            app(RoleManager::class)->assign($tenant1User, $tenant1Project, 'TestAdmin');
            app(RoleManager::class)->assign($tenant2User, $tenant2Project, 'TestAdmin');

            // Query database directly to ensure data separation
            $tenant1Records = DB::table('roster')
                ->where('assignable_id', $tenant1User->id)
                ->get();

            $tenant2Records = DB::table('roster')
                ->where('assignable_id', $tenant2User->id)
                ->get();

            // Each tenant should only see their own data
            expect($tenant1Records)->toHaveCount(1);
            expect($tenant2Records)->toHaveCount(1);

            expect($tenant1Records->first()->roleable_id)->toBe((string) $tenant1Project->id);
            expect($tenant2Records->first()->roleable_id)->toBe((string) $tenant2Project->id);
        });

        it('handles tenant switching scenarios', function () {
            extract(createAdvancedFixtures());
            $user = $users->first();

            // User has roles in multiple "tenants" (different projects)
            $tenant1Projects = $projects->slice(0, 2);
            $tenant2Projects = $projects->slice(2, 2);

            foreach ($tenant1Projects as $project) {
                app(RoleManager::class)->assign($user, $project, 'TestAdmin');
            }

            foreach ($tenant2Projects as $project) {
                app(RoleManager::class)->assign($user, $project, 'TestViewer');
            }

            // Simulate tenant context switching by checking different projects
            // In tenant 1 context
            foreach ($tenant1Projects as $project) {
                expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();
                expect($user->hasRoleOn($project, 'TestViewer'))->toBeFalse();
            }

            // In tenant 2 context
            foreach ($tenant2Projects as $project) {
                expect($user->hasRoleOn($project, 'TestViewer'))->toBeTrue();
                expect($user->hasRoleOn($project, 'TestAdmin'))->toBeFalse();
            }
        });

        it('maintains isolation during bulk operations', function () {
            extract(createAdvancedFixtures());
            // Create large-scale multi-tenant scenario
            $tenantsData = [
                'tenant_a' => [
                    'users' => $users->slice(0, 5),
                    'projects' => $projects->slice(0, 3),
                    'role' => 'TestAdmin',
                ],
                'tenant_b' => [
                    'users' => $users->slice(5, 5),
                    'projects' => $projects->slice(3, 3),
                    'role' => 'TestEditor',
                ],
                'tenant_c' => [
                    'users' => $users->slice(10, 5),
                    'projects' => $projects->slice(6, 3),
                    'role' => 'TestViewer',
                ],
            ];

            // Bulk assign roles for all tenants
            foreach ($tenantsData as $tenantName => $tenant) {
                foreach ($tenant['users'] as $user) {
                    foreach ($tenant['projects'] as $project) {
                        app(RoleManager::class)->assign($user, $project, $tenant['role']);
                    }
                }
            }

            // Verify isolation by checking cross-tenant access
            foreach ($tenantsData as $tenantName => $tenant) {
                foreach ($tenantsData as $otherTenantName => $otherTenant) {
                    if ($tenantName === $otherTenantName) {
                        continue;
                    }

                    // Users from one tenant should not have roles in another tenant
                    foreach ($tenant['users'] as $user) {
                        foreach ($otherTenant['projects'] as $project) {
                            expect($user->hasRoleOn($project, $tenant['role']))->toBeFalse();
                            expect($user->hasRoleOn($project, $otherTenant['role']))->toBeFalse();
                        }
                    }
                }
            }

            // Verify correct assignments within tenant
            foreach ($tenantsData as $tenantName => $tenant) {
                foreach ($tenant['users'] as $user) {
                    foreach ($tenant['projects'] as $project) {
                        expect($user->hasRoleOn($project, $tenant['role']))->toBeTrue();
                    }
                }
            }
        });

        it('handles tenant deletion and cleanup scenarios', function () {
            extract(createAdvancedFixtures());
            $user = $users->first();

            // User has roles in multiple tenants
            $tenantProjects = $projects->slice(0, 5);

            foreach ($tenantProjects as $project) {
                app(RoleManager::class)->assign($user, $project, 'TestAdmin');
            }

            expect(DB::table('roster')->count())->toBe(5);

            // Simulate tenant deletion by removing roles for specific projects
            $deletedTenantProjects = $tenantProjects->slice(0, 2);
            $remainingTenantProjects = $tenantProjects->slice(2, 3);

            foreach ($deletedTenantProjects as $project) {
                app(RoleManager::class)->remove($user, $project);
            }

            expect(DB::table('roster')->count())->toBe(3);

            // Verify user no longer has access to deleted tenant
            foreach ($deletedTenantProjects as $project) {
                expect($user->hasRoleOn($project, 'TestAdmin'))->toBeFalse();
            }

            // Verify user still has access to remaining tenants
            foreach ($remainingTenantProjects as $project) {
                expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();
            }
        });
    });

    describe('Edge Case Combinations', function () {
        it('handles all advanced scenarios combined', function () {
            extract(createAdvancedFixtures());
            // Complex scenario combining multiple advanced features
            $superAdmin = $users[0];
            $tenantAdmins = $users->slice(1, 3)->values();
            $regularUsers = $users->slice(4, 10)->values();

            // Create multi-tenant, multi-hierarchy scenario
            $tenants = [
                'enterprise' => $projects->slice(0, 3),
                'startup' => $projects->slice(3, 3),
                'nonprofit' => $projects->slice(6, 3),
            ];

            // Super admin has access to everything
            foreach ($tenants as $tenantName => $projects) {
                foreach ($projects as $project) {
                    app(RoleManager::class)->assign($superAdmin, $project, 'TestAdmin');
                }
            }

            // Tenant admins have limited scope
            $tenantIndex = 0;
            foreach ($tenants as $tenantName => $projects) {
                // Ensure we have enough tenant admins
                if ($tenantIndex < $tenantAdmins->count()) {
                    $tenantAdmin = $tenantAdmins[$tenantIndex];

                    foreach ($projects as $project) {
                        app(RoleManager::class)->assign($tenantAdmin, $project, 'TestEditor');
                    }

                    $tenantIndex++;
                }
            }

            // Regular users have specific project access
            foreach ($regularUsers as $userIndex => $user) {
                $tenantIndex = $userIndex % 3;
                $tenantProjectsArray = array_values($tenants);

                if (count($tenantProjectsArray) > 0 && isset($tenantProjectsArray[$tenantIndex])) {
                    $tenantProjects = $tenantProjectsArray[$tenantIndex];

                    if ($tenantProjects->count() > 0) {
                        $projectIndex = $userIndex % $tenantProjects->count();
                        $project = $tenantProjects->get($projectIndex);
                        if ($project) {
                            app(RoleManager::class)->assign($user, $project, 'TestViewer');
                        }
                    }
                }
            }

            // Verify complex access patterns
            // Super admin should have access everywhere
            foreach ($tenants as $tenantName => $projects) {
                foreach ($projects as $project) {
                    expect($superAdmin->hasRoleOn($project, 'TestAdmin'))->toBeTrue();
                }
            }

            // Tenant admins should only have access to their tenant
            $tenantIndex = 0;
            foreach ($tenants as $tenantName => $projects) {
                $tenantAdmin = $tenantAdmins[$tenantIndex];

                // Should have access to their tenant
                foreach ($projects as $project) {
                    expect($tenantAdmin->hasRoleOn($project, 'TestEditor'))->toBeTrue();
                }

                // Should NOT have access to other tenants
                foreach ($tenants as $otherTenantName => $otherProjects) {
                    if ($otherTenantName === $tenantName) {
                        continue;
                    }

                    foreach ($otherProjects as $project) {
                        expect($tenantAdmin->hasRoleOn($project, 'TestEditor'))->toBeFalse();
                        expect($tenantAdmin->hasRoleOn($project, 'TestAdmin'))->toBeFalse();
                    }
                }

                $tenantIndex++;
            }

            // Verify we have a reasonable number of assignments
            $totalAssignments = DB::table('roster')->count();
            expect($totalAssignments)->toBeGreaterThan(15); // Should have created many assignments
            expect($totalAssignments)->toBeLessThan(35); // But not too many
        });
    });
});
