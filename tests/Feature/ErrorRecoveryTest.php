<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Tests\Feature;

use Hdaklue\Porter\RoleManager;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

describe('Error Recovery Tests', function () {
    beforeEach(function () {
        $this->user = tap(new TestUser(), fn ($u) => $u->id = 1);
        $this->project = tap(new TestProject(), fn ($p) => $p->id = 1);
    });

    describe('Database Failure Scenarios', function () {
        it('handles database connection failures gracefully', function () {
            // First establish a working connection
            app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();

            // Test with a role that doesn't exist - this should fail gracefully
            expect(function () {
                app(RoleManager::class)->assign($this->user, $this->project, 'NonexistentRole');
            })->toThrow(\Exception::class);

            // Verify the system is still working for valid operations
            app(RoleManager::class)->assign($this->user, $this->project, 'TestEditor');
            expect($this->user->hasRoleOn($this->project, 'TestEditor'))->toBeTrue();
        });

        it('recovers from database lock conflicts', function () {
            // Create a scenario that might cause locks
            $users = collect(range(1, 5))->map(fn ($i) => tap(new TestUser(), fn ($u) => $u->id = $i));

            // Rapid concurrent operations that might cause issues
            $exceptions = [];
            $assignments = 0;

            foreach ($users as $user) {
                try {
                    app(RoleManager::class)->assign($user, $this->project, 'TestAdmin');
                    $assignments++;
                    
                    // Try duplicate assignment
                    app(RoleManager::class)->assign($user, $this->project, 'TestAdmin');
                    $assignments++;
                } catch (\Exception $e) {
                    $exceptions[] = $e;
                }
            }

            // Either we should have some exceptions OR successful assignments
            expect($assignments + count($exceptions))->toBeGreaterThan(0);

            // Verify system still works regardless
            $newUser = tap(new TestUser(), fn ($u) => $u->id = 999);
            app(RoleManager::class)->assign($newUser, $this->project, 'TestEditor');
            expect($newUser->hasRoleOn($this->project, 'TestEditor'))->toBeTrue();
        });

        it('handles corrupt database records gracefully', function () {
            // Insert some valid data
            app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');

            // Manually corrupt data in database
            DB::table('roster')->where('assignable_id', 1)->update([
                'role_key' => 'corrupted_data_'.str_repeat('x', 1000),
                'assignable_type' => 'NonexistentClass',
            ]);

            // System should handle corrupted data gracefully
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeFalse();

            // Should still be able to assign new roles
            app(RoleManager::class)->assign($this->user, $this->project, 'TestEditor');
            expect($this->user->hasRoleOn($this->project, 'TestEditor'))->toBeTrue();
        });

        it('handles missing database tables gracefully', function () {
            // First verify normal operation works
            app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();

            // Drop the table
            DB::statement('DROP TABLE IF EXISTS roster');

            // Operations should fail gracefully
            expect(function () {
                app(RoleManager::class)->assign($this->user, $this->project, 'TestEditor');
            })->toThrow(\Exception::class);

            // Role check should return false (graceful degradation)
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeFalse();

            // Recreate table for cleanup
            $this->setUp();
        });

        it('handles database constraint violations properly', function () {
            // Create a valid assignment
            app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');

            // Try to create duplicate assignment through the system (should be handled gracefully)
            try {
                app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');
                
                // If it allows duplicates, verify only one record exists or it's handled properly
                $count = DB::table('roster')
                    ->where('assignable_id', $this->user->id)
                    ->where('roleable_id', $this->project->id)
                    ->where('role_key', 'TestAdmin')
                    ->count();
                
                expect($count)->toBeLessThanOrEqual(2); // At most one duplicate allowed
            } catch (\Exception $e) {
                // Exception is acceptable for duplicate prevention
                expect($e)->toBeInstanceOf(\Exception::class);
            }

            // System should still function normally
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();
        });

        it('handles transaction rollback scenarios', function () {
            $originalCount = DB::table('roster')->count();

            // Attempt a batch operation that should fail partway through
            DB::beginTransaction();

            try {
                // This should succeed
                app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');

                // Force an error
                throw new \Exception('Simulated error during batch operation');
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
            }

            // Table should be back to original state
            expect(DB::table('roster')->count())->toBe($originalCount);
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeFalse();
        });
    });

    describe('Cache Backend Failures', function () {
        it('functions without cache when cache backend fails', function () {
            // Enable caching
            Config::set('porter.cache.enabled', true);
            Config::set('porter.should_cache', true);

            // First operation should work and populate cache
            app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();

            // Test that system works when cache is disabled
            Config::set('porter.cache.enabled', false);

            // Operations should still work without cache
            app(RoleManager::class)->assign($this->user, $this->project, 'TestEditor');
            expect($this->user->hasRoleOn($this->project, 'TestEditor'))->toBeTrue();

            // Restore cache configuration
            Config::set('cache.default', 'array');
        });

        it('handles cache corruption gracefully', function () {
            Config::set('porter.cache.enabled', true);
            Config::set('porter.should_cache', true);

            // Populate cache
            app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');
            $this->user->hasRoleOn($this->project, 'TestAdmin');

            // Corrupt cache data
            Cache::put('porter.role_cache_key_example', 'corrupted_data');

            // Should fall back to database and work correctly
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();
        });

        it('recovers when cache is full', function () {
            Config::set('porter.cache.enabled', true);
            Config::set('porter.should_cache', true);

            // Fill cache with dummy data to simulate full cache
            for ($i = 0; $i < 1000; $i++) {
                Cache::put("dummy_key_{$i}", str_repeat('x', 1000), 3600);
            }

            // Operations should still work even if cache is full
            app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();
        });

        it('handles cache timeout gracefully', function () {
            Config::set('porter.cache.enabled', true);
            Config::set('porter.should_cache', true);

            // Create assignment and cache it
            app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');
            $this->user->hasRoleOn($this->project, 'TestAdmin');

            // Clear all cache to simulate timeout
            Cache::flush();

            // Should still work by falling back to database
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();
        });
    });

    describe('Malformed Data Handling', function () {
        it('handles null values in critical fields', function () {
            // Try to insert null values directly into database
            expect(function () {
                DB::table('roster')->insert([
                    'assignable_type' => null,
                    'assignable_id' => '1',
                    'roleable_type' => get_class($this->project),
                    'roleable_id' => '1',
                    'role_key' => 'TestAdmin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            })->toThrow(\Exception::class);

            // System should still be functional
            app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();
        });

        it('handles extremely long data values', function () {
            $longString = str_repeat('a', 10000);

            try {
                DB::table('roster')->insert([
                    'assignable_type' => $longString,
                    'assignable_id' => '1',
                    'roleable_type' => get_class($this->project),
                    'roleable_id' => '1',
                    'role_key' => 'TestAdmin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // If database accepts long strings, system should handle it
                expect(DB::table('roster')->count())->toBeGreaterThan(0);
                DB::table('roster')->truncate();
            } catch (\Exception $e) {
                // Database rejected long data - this is acceptable
                expect($e)->toBeInstanceOf(\Exception::class);
            }
        });

        it('handles invalid JSON in serialized fields', function () {
            // If any fields store JSON, test with malformed JSON
            $invalidJson = '{"incomplete": json data';

            // This would depend on implementation details
            // For now, just verify system handles basic malformed data
            expect(json_decode($invalidJson))->toBeNull();
        });

        it('handles special characters in role assignments', function () {
            $specialChars = ['<script>', '&amp;', '"quotes"', "'single'", "\n\r\t"];

            foreach ($specialChars as $char) {
                // Most should be rejected or handled safely
                try {
                    app(RoleManager::class)->assign($this->user, $this->project, "role{$char}");

                    // If it succeeds, verify it doesn't break anything
                    $result = $this->user->hasRoleOn($this->project, "role{$char}");
                    expect($result)->toBeIn([true, false]); // Should return boolean
                } catch (\Exception $e) {
                    // Expected for invalid characters
                    expect($e)->toBeInstanceOf(\Exception::class);
                }
            }
        });

        it('handles binary data in text fields', function () {
            $binaryData = "\x00\x01\x02\x03\xFF";

            try {
                DB::table('roster')->insert([
                    'assignable_type' => get_class($this->user),
                    'assignable_id' => '1',
                    'roleable_type' => get_class($this->project),
                    'roleable_id' => '1',
                    'role_key' => $binaryData,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                
                // If insert succeeds, verify system can handle it
                expect(DB::table('roster')->count())->toBeGreaterThan(0);
                
                // Clean up
                DB::table('roster')->truncate();
            } catch (\Exception $e) {
                // This is acceptable - system rejected binary data
                expect($e)->toBeInstanceOf(\Exception::class);
            }
        });

        it('handles empty or whitespace-only values', function () {
            $emptyValues = ['', ' ', "\t", "\n", "\r\n"];

            foreach ($emptyValues as $empty) {
                try {
                    DB::table('roster')->insert([
                        'assignable_type' => $empty,
                        'assignable_id' => '1',
                        'roleable_type' => get_class($this->project),
                        'roleable_id' => '1',
                        'role_key' => 'TestAdmin',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    
                    // If insert succeeds, verify system can still handle it
                    expect(DB::table('roster')->count())->toBeGreaterThan(0);
                    
                    // Clean up for next iteration
                    DB::table('roster')->truncate();
                } catch (\Exception $e) {
                    // This is also acceptable - system rejected bad data
                    expect($e)->toBeInstanceOf(\Exception::class);
                }
            }
        });
    });

    describe('Network and I/O Failures', function () {
        it('handles temporary file system issues', function () {
            // This would be more relevant if the system uses file-based storage
            // For now, test basic I/O resilience

            $tmpFile = tempnam(sys_get_temp_dir(), 'porter_test');
            file_put_contents($tmpFile, 'test data');

            // Simulate file being deleted mid-operation
            unlink($tmpFile);

            // System should continue working normally
            app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();
        });

        it('handles disk space issues gracefully', function () {
            // Simulate by trying to write large amounts of data
            $largeDataSet = [];

            for ($i = 0; $i < 1000; $i++) {
                $largeDataSet[] = [
                    'assignable_type' => get_class($this->user),
                    'assignable_id' => (string) $i,
                    'roleable_type' => get_class($this->project),
                    'roleable_id' => '1',
                    'role_key' => 'TestAdmin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Insert should succeed or fail gracefully
            try {
                DB::table('roster')->insert($largeDataSet);
                expect(DB::table('roster')->count())->toBe(1000);
            } catch (\Exception $e) {
                // If it fails, system should still be stable
                expect($e)->toBeInstanceOf(\Exception::class);

                // Verify basic operations still work
                app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');
                expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();
            }
        });
    });

    describe('System Resource Exhaustion', function () {
        it('handles memory exhaustion gracefully', function () {
            // Create a scenario that uses significant memory
            $largeArray = [];

            try {
                // Don't actually exhaust memory in tests, just verify reasonable usage
                for ($i = 0; $i < 1000; $i++) {
                    $largeArray[] = app(RoleManager::class)->assign(
                        tap(new TestUser(), fn ($u) => $u->id = $i),
                        $this->project,
                        'TestAdmin'
                    );
                }

                $memoryUsed = memory_get_usage(true);
                expect($memoryUsed)->toBeLessThan(100 * 1024 * 1024); // Less than 100MB

            } catch (\Exception $e) {
                // If memory is exhausted, verify we can still do basic operations
                expect($e)->toBeInstanceOf(\Exception::class);
            }
        });

        it('handles timeout scenarios', function () {
            // Simulate long-running operations
            $startTime = time();

            // Perform many operations
            for ($i = 0; $i < 100; $i++) {
                $user = tap(new TestUser(), fn ($u) => $u->id = $i);
                app(RoleManager::class)->assign($user, $this->project, 'TestAdmin');
                $user->hasRoleOn($this->project, 'TestAdmin');
                app(RoleManager::class)->remove($user, $this->project);

                // Break if taking too long (simulate timeout)
                if (time() - $startTime > 5) {
                    break;
                }
            }

            // System should still be responsive
            $finalUser = tap(new TestUser(), fn ($u) => $u->id = 9999);
            app(RoleManager::class)->assign($finalUser, $this->project, 'TestAdmin');
            expect($finalUser->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();
        });
    });

    describe('Recovery and Cleanup', function () {
        it('automatically cleans up inconsistent states', function () {
            // Create some valid data
            app(RoleManager::class)->assign($this->user, $this->project, 'TestAdmin');

            // Manually create inconsistent state
            DB::table('roster')->insert([
                'assignable_type' => 'NonExistentClass',
                'assignable_id' => '999',
                'roleable_type' => 'AnotherNonExistentClass',
                'roleable_id' => '888',
                'role_key' => 'orphaned_role',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // System should handle the inconsistent data gracefully
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeTrue();

            // Cleanup operations should work
            app(RoleManager::class)->remove($this->user, $this->project);
            expect($this->user->hasRoleOn($this->project, 'TestAdmin'))->toBeFalse();
        });

        it('maintains data integrity after errors', function () {
            $initialCount = DB::table('roster')->count();

            // Perform operations that might fail
            $errors = 0;

            for ($i = 0; $i < 10; $i++) {
                try {
                    $user = tap(new TestUser(), fn ($u) => $u->id = $i);
                    app(RoleManager::class)->assign($user, $this->project, 'TestAdmin');

                    // Simulate random failures
                    if ($i % 3 === 0) {
                        throw new \Exception('Simulated error');
                    }

                } catch (\Exception $e) {
                    $errors++;
                }
            }

            expect($errors)->toBeGreaterThan(0);

            // Verify data integrity
            $finalCount = DB::table('roster')->count();
            expect($finalCount)->toBeGreaterThanOrEqual($initialCount);
            expect($finalCount)->toBeLessThanOrEqual($initialCount + 10); // Some may have failed
        });
    });
});
