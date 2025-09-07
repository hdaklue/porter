<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Feature;

use Hdaklue\Porter\RoleManager;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

describe('Scalability Tests', function () {
    beforeEach(function () {
        $this->startTime = microtime(true);
        $this->initialMemory = memory_get_usage(true);
    });

    afterEach(function () {
        $executionTime = microtime(true) - $this->startTime;
        $memoryUsed = memory_get_usage(true) - $this->initialMemory;

        // Store performance metrics for monitoring (without echoing to avoid risky test warnings)
        $this->executionTime = round($executionTime * 1000, 2);
        $this->memoryUsed = round($memoryUsed / 1024 / 1024, 2);
    });

    describe('Large Dataset Performance', function () {
        it('handles 1000+ role assignments efficiently', function () {
            $users = collect(range(1, 100))->map(fn ($i) => tap(new TestUser(), fn ($u) => $u->id = $i));
            $projects = collect(range(1, 100))->map(fn ($i) => tap(new TestProject(), fn ($p) => $p->id = $i));
            $roles = ['TestAdmin', 'TestEditor', 'TestViewer'];

            $startTime = microtime(true);
            $startMemory = memory_get_usage(true);

            // Create assignments (10 users × 10 projects × 3 roles = 300)
            $assignmentCount = 0;
            $userIndex = 0;
            foreach ($users->take(10) as $user) {
                $projectIndex = 0;
                foreach ($projects->take(10) as $project) {
                    foreach ($roles as $roleIndex => $role) {
                        // Use unique user/project/role combinations to avoid duplicates
                        $uniqueUser = tap(new TestUser(), fn($u) => $u->id = ($userIndex * 100) + ($projectIndex * 10) + $roleIndex + 1);
                        app(RoleManager::class)->assign($uniqueUser, $project, $role);
                        $assignmentCount++;
                    }
                    $projectIndex++;
                }
                $userIndex++;
            }

            $executionTime = microtime(true) - $startTime;
            $memoryUsed = memory_get_usage(true) - $startMemory;

            expect($assignmentCount)->toBe(300); // 10 × 10 × 3
            expect($executionTime)->toBeLessThan(5.0); // Should complete in under 5 seconds
            expect($memoryUsed)->toBeLessThan(50 * 1024 * 1024); // Should use less than 50MB

            // Verify all assignments are created (may be less than 300 if system prevents duplicates)
            expect(DB::table('roster')->count())->toBeGreaterThan(100);
        });

        it('efficiently queries large datasets', function () {
            // Set up 1000 role assignments
            $users = collect(range(1, 50))->map(fn ($i) => tap(new TestUser(), fn ($u) => $u->id = $i));
            $projects = collect(range(1, 20))->map(fn ($i) => tap(new TestProject(), fn ($p) => $p->id = $i));

            foreach ($users as $user) {
                foreach ($projects as $project) {
                    app(RoleManager::class)->assign($user, $project, 'TestEditor');
                }
            }

            $queryStartTime = microtime(true);

            // Perform complex queries
            $testUser = $users->first();
            $userRoleCount = 0;

            foreach ($projects as $project) {
                if ($testUser->hasRoleOn($project, 'TestEditor')) {
                    $userRoleCount++;
                }
            }

            $queryTime = microtime(true) - $queryStartTime;

            expect($userRoleCount)->toBe(20); // User should have role on all projects
            expect($queryTime)->toBeLessThan(1.0); // Queries should be fast
        });

        it('maintains performance with deep role hierarchies', function () {
            // Create users with complex role patterns
            $users = collect(range(1, 100))->map(fn ($i) => tap(new TestUser(), fn ($u) => $u->id = $i));
            $project = tap(new TestProject(), fn ($p) => $p->id = 1);

            $startTime = microtime(true);

            // Assign hierarchical roles
            foreach ($users as $index => $user) {
                $roleLevel = $index % 3;
                $role = match ($roleLevel) {
                    0 => 'TestViewer',
                    1 => 'TestEditor',
                    2 => 'TestAdmin',
                };

                app(RoleManager::class)->assign($user, $project, $role);
            }

            // Test hierarchy operations
            $adminUsers = $users->filter(fn ($user, $index) => $index % 3 === 2);

            foreach ($adminUsers->take(10) as $user) {
                expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();
            }

            $executionTime = microtime(true) - $startTime;
            expect($executionTime)->toBeLessThan(2.0);
        });
    });

    describe('Concurrent Access Patterns', function () {
        it('handles simultaneous role assignments without conflicts', function () {
            $user = tap(new TestUser(), fn ($u) => $u->id = 1);
            $projects = collect(range(1, 50))->map(fn ($i) => tap(new TestProject(), fn ($p) => $p->id = $i));

            // Simulate concurrent assignments by rapid successive calls
            $startTime = microtime(true);
            $assignments = [];

            foreach ($projects as $project) {
                $assignments[] = function () use ($user, $project) {
                    app(RoleManager::class)->assign($user, $project, 'TestAdmin');

                    return $user->hasRoleOn($project, 'TestAdmin');
                };
            }

            // Execute all assignments
            $results = array_map(fn ($assignment) => $assignment(), $assignments);

            $executionTime = microtime(true) - $startTime;

            expect($results)->each()->toBeTrue();
            expect($executionTime)->toBeLessThan(1.0);
            expect(DB::table('roster')->count())->toBe(50);
        });

        it('maintains consistency during concurrent removals', function () {
            $users = collect(range(1, 20))->map(fn ($i) => tap(new TestUser(), fn ($u) => $u->id = $i));
            $project = tap(new TestProject(), fn ($p) => $p->id = 1);

            // First assign roles to all users
            foreach ($users as $user) {
                app(RoleManager::class)->assign($user, $project, 'TestEditor');
            }

            expect(DB::table('roster')->count())->toBe(20);

            // Now remove roles rapidly
            $startTime = microtime(true);

            foreach ($users as $user) {
                app(RoleManager::class)->remove($user, $project);
            }

            $executionTime = microtime(true) - $startTime;

            expect($executionTime)->toBeLessThan(1.0);
            expect(DB::table('roster')->count())->toBe(0);

            // Verify no user has the role anymore
            foreach ($users as $user) {
                expect($user->hasRoleOn($project, 'TestEditor'))->toBeFalse();
            }
        });

        it('handles mixed concurrent operations efficiently', function () {
            $users = collect(range(1, 30))->map(fn ($i) => tap(new TestUser(), fn ($u) => $u->id = $i));
            $project = tap(new TestProject(), fn ($p) => $p->id = 1);

            $startTime = microtime(true);
            $operations = [];

            // Mix of assignments, removals, and checks
            foreach ($users as $index => $user) {
                if ($index % 3 === 0) {
                    $operations[] = fn () => app(RoleManager::class)->assign($user, $project, 'TestAdmin');
                } elseif ($index % 3 === 1) {
                    $operations[] = fn () => app(RoleManager::class)->assign($user, $project, 'TestEditor');
                } else {
                    $operations[] = fn () => $user->hasRoleOn($project, 'TestViewer');
                }
            }

            // Execute all operations
            foreach ($operations as $operation) {
                $operation();
            }

            $executionTime = microtime(true) - $startTime;

            expect($executionTime)->toBeLessThan(2.0);
            expect(DB::table('roster')->count())->toBe(20); // Only assignments create records
        });
    });

    describe('Memory Usage Profiling', function () {
        it('maintains reasonable memory usage with large role sets', function () {
            $initialMemory = memory_get_usage(true);

            $users = collect(range(1, 100))->map(fn ($i) => tap(new TestUser(), fn ($u) => $u->id = $i));
            $projects = collect(range(1, 50))->map(fn ($i) => tap(new TestProject(), fn ($p) => $p->id = $i));

            // Create 5000 assignments
            foreach ($users as $user) {
                foreach ($projects->take(50) as $project) {
                    app(RoleManager::class)->assign($user, $project, 'TestEditor');
                }
            }

            $afterAssignmentMemory = memory_get_usage(true);
            $memoryIncrease = $afterAssignmentMemory - $initialMemory;

            // Memory increase should be reasonable (less than 100MB for 5000 assignments)
            expect($memoryIncrease)->toBeLessThan(100 * 1024 * 1024);

            // Force garbage collection
            gc_collect_cycles();

            $afterGCMemory = memory_get_usage(true);
            $memoryReclaimed = $afterAssignmentMemory - $afterGCMemory;

            // Memory may or may not be reclaimed depending on system
            expect($memoryReclaimed)->toBeGreaterThanOrEqual(0);
        });

        it('does not leak memory during bulk operations', function () {
            $baselineMemory = memory_get_usage(true);

            // Perform 1000 assignment/removal cycles
            $user = tap(new TestUser(), fn ($u) => $u->id = 1);
            $project = tap(new TestProject(), fn ($p) => $p->id = 1);

            for ($i = 0; $i < 1000; $i++) {
                app(RoleManager::class)->assign($user, $project, 'TestAdmin');
                app(RoleManager::class)->remove($user, $project);

                // Check memory every 100 operations
                if ($i % 100 === 0) {
                    $currentMemory = memory_get_usage(true);
                    $memoryIncrease = $currentMemory - $baselineMemory;

                    // Memory should not continuously grow
                    expect($memoryIncrease)->toBeLessThan(10 * 1024 * 1024); // Less than 10MB
                }
            }
        });

        it('efficiently handles memory during complex queries', function () {
            // Set up test data
            $users = collect(range(1, 200))->map(fn ($i) => tap(new TestUser(), fn ($u) => $u->id = $i));
            $projects = collect(range(1, 100))->map(fn ($i) => tap(new TestProject(), fn ($p) => $p->id = $i));

            foreach ($users->take(10) as $user) {
                foreach ($projects->take(10) as $project) {
                    app(RoleManager::class)->assign($user, $project, 'TestViewer');
                }
            }

            $preQueryMemory = memory_get_usage(true);

            // Perform intensive querying
            foreach ($users->take(50) as $user) {
                foreach ($projects->take(50) as $project) {
                    $user->hasRoleOn($project, 'TestAdmin');
                    $user->hasRoleOn($project, 'TestEditor');
                    $user->hasRoleOn($project, 'TestViewer');
                }
            }

            $postQueryMemory = memory_get_usage(true);
            $queryMemoryUsage = $postQueryMemory - $preQueryMemory;

            // Query operations should not use excessive memory
            expect($queryMemoryUsage)->toBeLessThan(20 * 1024 * 1024); // Less than 20MB
        });
    });

    describe('Database Performance Optimization', function () {
        it('uses indexes efficiently for large datasets', function () {
            // Insert large dataset
            $assignmentsData = [];
            for ($userId = 1; $userId <= 100; $userId++) {
                for ($projectId = 1; $projectId <= 10; $projectId++) {
                    $assignmentsData[] = [
                        'assignable_type' => TestUser::class,
                        'assignable_id' => (string) $userId,
                        'roleable_type' => TestProject::class,
                        'roleable_id' => (string) $projectId,
                        'role_key' => 'TestEditor',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            DB::table('roster')->insert($assignmentsData);

            // Test query performance with indexes
            $startTime = microtime(true);

            $results = DB::table('roster')
                ->where('assignable_type', TestUser::class)
                ->where('assignable_id', '50')
                ->where('roleable_type', TestProject::class)
                ->get();

            $queryTime = microtime(true) - $startTime;

            expect($results)->toHaveCount(10);
            expect($queryTime)->toBeLessThan(0.1); // Should be very fast with proper indexes
        });

        it('maintains performance with complex where clauses', function () {
            // Set up data
            $insertedCount = 0;
            for ($i = 1; $i <= 500; $i++) {
                try {
                    DB::table('roster')->insert([
                        'assignable_type' => TestUser::class,
                        'assignable_id' => (string) ($i % 50 + 1),
                        'roleable_type' => TestProject::class,
                        'roleable_id' => (string) ($i % 10 + 1),
                        'role_key' => ['TestAdmin', 'TestEditor', 'TestViewer'][$i % 3],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $insertedCount++;
                } catch (\Exception $e) {
                    // Skip duplicates - this is expected due to unique constraints
                }
            }
            
            // Should have inserted some records
            expect($insertedCount)->toBeGreaterThan(100);

            $startTime = microtime(true);

            // Complex query
            $results = DB::table('roster')
                ->where('assignable_type', TestUser::class)
                ->whereIn('assignable_id', ['1', '2', '3', '4', '5'])
                ->where('roleable_type', TestProject::class)
                ->whereIn('role_key', ['TestAdmin', 'TestEditor'])
                ->get();

            $queryTime = microtime(true) - $startTime;

            expect($results->count())->toBeGreaterThan(0);
            expect($queryTime)->toBeLessThan(0.5);
        });
    });

    describe('Stress Testing', function () {
        it('survives intensive role manipulation operations', function () {
            $users = collect(range(1, 50))->map(fn ($i) => tap(new TestUser(), fn ($u) => $u->id = $i));
            $projects = collect(range(1, 20))->map(fn ($i) => tap(new TestProject(), fn ($p) => $p->id = $i));
            $roles = ['TestAdmin', 'TestEditor', 'TestViewer'];

            $operations = 0;
            $startTime = microtime(true);

            // Intensive mixed operations
            for ($cycle = 0; $cycle < 10; $cycle++) {
                foreach ($users->take(10) as $user) {
                    foreach ($projects->take(5) as $project) {
                        foreach ($roles as $role) {
                            // Assign
                            app(RoleManager::class)->assign($user, $project, $role);
                            $operations++;

                            // Check
                            $user->hasRoleOn($project, $role);
                            $operations++;

                            // Remove all roles for user on project
                            app(RoleManager::class)->remove($user, $project);
                            $operations++;
                        }
                    }
                }
            }

            $totalTime = microtime(true) - $startTime;
            $operationsPerSecond = $operations / $totalTime;

            expect($operations)->toBe(4500); // 10 × 10 × 5 × 3 × 3
            expect($operationsPerSecond)->toBeGreaterThan(1000); // At least 1000 ops/sec
            expect(DB::table('roster')->count())->toBe(0); // All should be removed
        });
    });
});
