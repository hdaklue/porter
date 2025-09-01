<?php

declare(strict_types=1);

use Hdaklue\LaraRbac\Enums\Role\RoleEnum;
use Illuminate\Support\Collection;

/**
 * RoleEnum Tests
 *
 * This test suite verifies the RoleEnum functionality including role hierarchy,
 * comparisons, labels, and filtering methods.
 */
describe('RoleEnum', function () {

    describe('Basic Enum Properties', function () {

        it('has correct string values for all roles', function () {
            expect(RoleEnum::ADMIN->value)->toBe('admin');
            expect(RoleEnum::MANAGER->value)->toBe('manager');
            expect(RoleEnum::EDITOR->value)->toBe('editor');
            expect(RoleEnum::CONTRIBUTOR->value)->toBe('contributor');
            expect(RoleEnum::VIEWER->value)->toBe('viewer');
            expect(RoleEnum::GUEST->value)->toBe('guest');
        });

        it('returns all available cases', function () {
            $cases = RoleEnum::cases();

            expect($cases)->toHaveCount(6)
                ->and($cases[0])->toBe(RoleEnum::ADMIN)
                ->and($cases[1])->toBe(RoleEnum::MANAGER)
                ->and($cases[2])->toBe(RoleEnum::EDITOR)
                ->and($cases[3])->toBe(RoleEnum::CONTRIBUTOR)
                ->and($cases[4])->toBe(RoleEnum::VIEWER)
                ->and($cases[5])->toBe(RoleEnum::GUEST);
        });

        it('can be instantiated from string values', function () {
            expect(RoleEnum::from('admin'))->toBe(RoleEnum::ADMIN);
            expect(RoleEnum::from('manager'))->toBe(RoleEnum::MANAGER);
            expect(RoleEnum::from('editor'))->toBe(RoleEnum::EDITOR);
            expect(RoleEnum::from('contributor'))->toBe(RoleEnum::CONTRIBUTOR);
            expect(RoleEnum::from('viewer'))->toBe(RoleEnum::VIEWER);
            expect(RoleEnum::from('guest'))->toBe(RoleEnum::GUEST);
        });

        it('can try to instantiate from string values safely', function () {
            expect(RoleEnum::tryFrom('admin'))->toBe(RoleEnum::ADMIN);
            expect(RoleEnum::tryFrom('invalid'))->toBeNull();
        });
    });

    describe('Role Hierarchy Levels', function () {

        it('returns correct hierarchical levels', function () {
            expect(RoleEnum::ADMIN->getLevel())->toBe(6);
            expect(RoleEnum::MANAGER->getLevel())->toBe(5);
            expect(RoleEnum::EDITOR->getLevel())->toBe(4);
            expect(RoleEnum::CONTRIBUTOR->getLevel())->toBe(3);
            expect(RoleEnum::VIEWER->getLevel())->toBe(2);
            expect(RoleEnum::GUEST->getLevel())->toBe(1);
        });

        it('maintains proper hierarchy order', function () {
            $roles = RoleEnum::cases();
            $levels = array_map(fn ($role) => $role->getLevel(), $roles);
            $sortedLevels = $levels;
            rsort($sortedLevels); // Sort descending

            expect($levels)->toBe($sortedLevels);
        });
    });

    describe('Role Comparison Methods', function () {

        it('correctly identifies higher roles', function () {
            expect(RoleEnum::ADMIN->isHigherThan(RoleEnum::MANAGER))->toBeTrue();
            expect(RoleEnum::ADMIN->isHigherThan(RoleEnum::GUEST))->toBeTrue();
            expect(RoleEnum::MANAGER->isHigherThan(RoleEnum::EDITOR))->toBeTrue();
            expect(RoleEnum::EDITOR->isHigherThan(RoleEnum::CONTRIBUTOR))->toBeTrue();
            expect(RoleEnum::CONTRIBUTOR->isHigherThan(RoleEnum::VIEWER))->toBeTrue();
            expect(RoleEnum::VIEWER->isHigherThan(RoleEnum::GUEST))->toBeTrue();

            // Negative cases
            expect(RoleEnum::GUEST->isHigherThan(RoleEnum::ADMIN))->toBeFalse();
            expect(RoleEnum::MANAGER->isHigherThan(RoleEnum::ADMIN))->toBeFalse();
            expect(RoleEnum::ADMIN->isHigherThan(RoleEnum::ADMIN))->toBeFalse(); // Same level
        });

        it('correctly identifies lower roles', function () {
            expect(RoleEnum::GUEST->isLowerThan(RoleEnum::ADMIN))->toBeTrue();
            expect(RoleEnum::VIEWER->isLowerThan(RoleEnum::CONTRIBUTOR))->toBeTrue();
            expect(RoleEnum::CONTRIBUTOR->isLowerThan(RoleEnum::EDITOR))->toBeTrue();
            expect(RoleEnum::EDITOR->isLowerThan(RoleEnum::MANAGER))->toBeTrue();
            expect(RoleEnum::MANAGER->isLowerThan(RoleEnum::ADMIN))->toBeTrue();

            // Negative cases
            expect(RoleEnum::ADMIN->isLowerThan(RoleEnum::GUEST))->toBeFalse();
            expect(RoleEnum::ADMIN->isLowerThan(RoleEnum::MANAGER))->toBeFalse();
            expect(RoleEnum::ADMIN->isLowerThan(RoleEnum::ADMIN))->toBeFalse(); // Same level
        });

        it('correctly identifies lower than or equal roles', function () {
            expect(RoleEnum::GUEST->isLowerThanOrEqual(RoleEnum::ADMIN))->toBeTrue();
            expect(RoleEnum::MANAGER->isLowerThanOrEqual(RoleEnum::ADMIN))->toBeTrue();
            expect(RoleEnum::ADMIN->isLowerThanOrEqual(RoleEnum::ADMIN))->toBeTrue(); // Same level

            // Negative cases
            expect(RoleEnum::ADMIN->isLowerThanOrEqual(RoleEnum::GUEST))->toBeFalse();
        });

        it('correctly identifies equal roles', function () {
            expect(RoleEnum::ADMIN->isEqualTo(RoleEnum::ADMIN))->toBeTrue();
            expect(RoleEnum::MANAGER->isEqualTo(RoleEnum::MANAGER))->toBeTrue();
            expect(RoleEnum::GUEST->isEqualTo(RoleEnum::GUEST))->toBeTrue();

            // Negative cases
            expect(RoleEnum::ADMIN->isEqualTo(RoleEnum::MANAGER))->toBeFalse();
            expect(RoleEnum::GUEST->isEqualTo(RoleEnum::ADMIN))->toBeFalse();
        });

        it('correctly identifies at least level roles', function () {
            expect(RoleEnum::ADMIN->isAtLeast(RoleEnum::GUEST))->toBeTrue();
            expect(RoleEnum::ADMIN->isAtLeast(RoleEnum::MANAGER))->toBeTrue();
            expect(RoleEnum::ADMIN->isAtLeast(RoleEnum::ADMIN))->toBeTrue(); // Same level
            expect(RoleEnum::MANAGER->isAtLeast(RoleEnum::EDITOR))->toBeTrue();

            // Negative cases
            expect(RoleEnum::GUEST->isAtLeast(RoleEnum::ADMIN))->toBeFalse();
            expect(RoleEnum::VIEWER->isAtLeast(RoleEnum::MANAGER))->toBeFalse();
        });
    });

    describe('Role Labels and Descriptions', function () {

        it('returns translated labels for all roles', function () {
            // Mock the translation function
            expect(RoleEnum::ADMIN->getLabel())->toBeString();
            expect(RoleEnum::MANAGER->getLabel())->toBeString();
            expect(RoleEnum::EDITOR->getLabel())->toBeString();
            expect(RoleEnum::CONTRIBUTOR->getLabel())->toBeString();
            expect(RoleEnum::VIEWER->getLabel())->toBeString();
            expect(RoleEnum::GUEST->getLabel())->toBeString();
        });

        it('returns translated descriptions for all roles', function () {
            expect(RoleEnum::ADMIN->getDescription())->toBeString();
            expect(RoleEnum::MANAGER->getDescription())->toBeString();
            expect(RoleEnum::EDITOR->getDescription())->toBeString();
            expect(RoleEnum::CONTRIBUTOR->getDescription())->toBeString();
            expect(RoleEnum::VIEWER->getDescription())->toBeString();
            expect(RoleEnum::GUEST->getDescription())->toBeString();
        });

        it('labels are not empty', function () {
            foreach (RoleEnum::cases() as $role) {
                expect($role->getLabel())->not()->toBeEmpty();
            }
        });

        it('descriptions are not empty', function () {
            foreach (RoleEnum::cases() as $role) {
                expect($role->getDescription())->not()->toBeEmpty();
            }
        });
    });

    describe('Static Collection Methods', function () {

        it('returns roles lower than or equal to given role', function () {
            $adminRoles = RoleEnum::whereLowerThanOrEqual(RoleEnum::ADMIN);

            expect($adminRoles)->toBeInstanceOf(Collection::class)
                ->and($adminRoles)->toHaveCount(6) // All roles are <= ADMIN
                ->and($adminRoles->keys()->toArray())->toBe([
                    'admin', 'manager', 'editor', 'contributor', 'viewer', 'guest',
                ]);
        });

        it('returns correct subset for manager role', function () {
            $managerRoles = RoleEnum::whereLowerThanOrEqual(RoleEnum::MANAGER);

            expect($managerRoles)->toHaveCount(5) // All except ADMIN
                ->and($managerRoles->keys()->toArray())->toBe([
                    'manager', 'editor', 'contributor', 'viewer', 'guest',
                ]);
        });

        it('returns only guest for guest role', function () {
            $guestRoles = RoleEnum::whereLowerThanOrEqual(RoleEnum::GUEST);

            expect($guestRoles)->toHaveCount(1)
                ->and($guestRoles->keys()->toArray())->toBe(['guest']);
        });

        it('getRolesLowerThanOrEqual returns correct structure', function () {
            $roles = RoleEnum::getRolesLowerThanOrEqual(RoleEnum::EDITOR);

            expect($roles)->toBeInstanceOf(Collection::class);

            $firstRole = $roles->first();
            expect($firstRole)->toHaveKeys(['value', 'label'])
                ->and($firstRole['value'])->toBeString()
                ->and($firstRole['label'])->toBeString();
        });

        it('getRolesLowerThanOrEqual excludes higher roles', function () {
            $roles = RoleEnum::getRolesLowerThanOrEqual(RoleEnum::CONTRIBUTOR);
            $values = $roles->pluck('value')->toArray();

            expect($values)->toContain('contributor', 'viewer', 'guest')
                ->and($values)->not()->toContain('admin', 'manager', 'editor');
        });
    });

    describe('Edge Cases and Error Handling', function () {

        it('handles invalid enum values gracefully', function () {
            expect(function () {
                RoleEnum::from('invalid_role');
            })->toThrow(ValueError::class);
        });

        it('comparison methods handle all role combinations', function () {
            $roles = RoleEnum::cases();

            foreach ($roles as $role1) {
                foreach ($roles as $role2) {
                    // These methods should not throw exceptions
                    $result1 = $role1->isHigherThan($role2);
                    $result2 = $role1->isLowerThan($role2);
                    $result3 = $role1->isLowerThanOrEqual($role2);
                    $result4 = $role1->isEqualTo($role2);
                    $result5 = $role1->isAtLeast($role2);

                    expect($result1)->toBeBool();
                    expect($result2)->toBeBool();
                    expect($result3)->toBeBool();
                    expect($result4)->toBeBool();
                    expect($result5)->toBeBool();
                }
            }
        });

        it('static methods handle all role inputs', function () {
            foreach (RoleEnum::cases() as $role) {
                $result1 = RoleEnum::whereLowerThanOrEqual($role);
                $result2 = RoleEnum::getRolesLowerThanOrEqual($role);

                expect($result1)->toBeInstanceOf(Collection::class);
                expect($result2)->toBeInstanceOf(Collection::class);
            }
        });
    });

    describe('Role Hierarchy Logic Validation', function () {

        it('ensures hierarchy is transitive', function () {
            // If A > B and B > C, then A > C
            expect(RoleEnum::ADMIN->isHigherThan(RoleEnum::MANAGER))->toBeTrue();
            expect(RoleEnum::MANAGER->isHigherThan(RoleEnum::GUEST))->toBeTrue();
            expect(RoleEnum::ADMIN->isHigherThan(RoleEnum::GUEST))->toBeTrue();
        });

        it('ensures hierarchy consistency in both directions', function () {
            $roles = RoleEnum::cases();

            foreach ($roles as $role1) {
                foreach ($roles as $role2) {
                    if ($role1->isHigherThan($role2)) {
                        expect($role2->isLowerThan($role1))->toBeTrue();
                    }

                    if ($role1->isLowerThan($role2)) {
                        expect($role2->isHigherThan($role1))->toBeTrue();
                    }
                }
            }
        });

        it('validates no role is both higher and lower than another', function () {
            $roles = RoleEnum::cases();

            foreach ($roles as $role1) {
                foreach ($roles as $role2) {
                    if ($role1 !== $role2) {
                        $isHigher = $role1->isHigherThan($role2);
                        $isLower = $role1->isLowerThan($role2);

                        expect($isHigher && $isLower)->toBeFalse();
                        expect($isHigher || $isLower)->toBeTrue(); // One must be true
                    }
                }
            }
        });

        it('validates role levels are unique', function () {
            $roles = RoleEnum::cases();
            $levels = array_map(fn ($role) => $role->getLevel(), $roles);
            $uniqueLevels = array_unique($levels);

            expect(count($levels))->toBe(count($uniqueLevels));
        });
    });

    describe('Performance and Memory', function () {

        it('efficiently handles multiple comparisons', function () {
            $startTime = microtime(true);

            // Perform many comparisons
            for ($i = 0; $i < 1000; $i++) {
                RoleEnum::ADMIN->isHigherThan(RoleEnum::GUEST);
                RoleEnum::MANAGER->isLowerThan(RoleEnum::ADMIN);
                RoleEnum::EDITOR->isAtLeast(RoleEnum::VIEWER);
            }

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;

            expect($executionTime)->toBeLessThan(10); // Less than 10ms for 3000 operations
        });

        it('static collection methods perform efficiently', function () {
            $startTime = microtime(true);

            // Test collection methods multiple times
            for ($i = 0; $i < 100; $i++) {
                RoleEnum::whereLowerThanOrEqual(RoleEnum::MANAGER);
                RoleEnum::getRolesLowerThanOrEqual(RoleEnum::EDITOR);
            }

            $endTime = microtime(true);
            $executionTime = ($endTime - $startTime) * 1000;

            expect($executionTime)->toBeLessThan(50); // Less than 50ms for 200 operations
        });
    });
});
