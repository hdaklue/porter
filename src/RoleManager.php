<?php

declare(strict_types=1);

namespace Hdaklue\Porter;

use DomainException;
use Hdaklue\Porter\Contracts\AssignableEntity;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\Contracts\RoleContract;
use Hdaklue\Porter\Contracts\RoleManagerContract;
use Hdaklue\Porter\Events\RoleAssigned;
use Hdaklue\Porter\Events\RoleChanged;
use Hdaklue\Porter\Events\RoleRemoved;
use Hdaklue\Porter\Models\Roster;
use Hdaklue\Porter\Roles\BaseRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RoleManager implements RoleManagerContract
{
    public function assign(AssignableEntity $user, RoleableEntity $target, string|RoleContract $role): void
    {
        DB::transaction(function () use ($user, $target, $role) {
            if (is_string($role)) {
                $this->ensureRoleExists($role);
                try {
                    $roleInstance = RoleFactory::make($role);
                } catch (InvalidArgumentException $e) {
                    $roleInstance = BaseRole::make($role);
                }
            } else {
                $roleInstance = $role;
            }

            $assignmentStrategy = config('porter.security.assignment_strategy', 'replace');

            if ($assignmentStrategy === 'replace') {
                $this->removeWithinTransaction($user, $target);
            }

            $encryptedKey = $roleInstance::getDbKey();

            $assignment = Roster::firstOrCreate([
                'assignable_type' => $user->getMorphClass(),
                'assignable_id' => $user->getKey(),
                'roleable_type' => $target->getMorphClass(),
                'roleable_id' => $target->getKey(),
                'role_key' => $encryptedKey,
            ]);

            if ($assignment->wasRecentlyCreated) {
                RoleAssigned::dispatch($user, $target, $roleInstance);
            }
        });

        $this->clearCache($target, $user);
    }

    public function remove(AssignableEntity $user, RoleableEntity $target): void
    {
        DB::transaction(function () use ($user, $target) {
            $this->removeWithinTransaction($user, $target);
        });

        $this->clearCache($target, $user);
    }

    public function check(AssignableEntity $assignableEntity, RoleableEntity $roleableEntity, RoleContract $roleContract): bool
    {
        return $this->performRoleCheck($assignableEntity, $roleableEntity, $roleContract::getDbKey());
    }

    public function changeRoleOn(AssignableEntity $user, RoleableEntity $target, string|RoleContract $role): void
    {
        DB::transaction(function () use ($user, $target, $role) {
            if (is_string($role)) {
                $this->ensureRoleExists($role);
                try {
                    $newRole = RoleFactory::make($role);
                } catch (InvalidArgumentException $e) {
                    $newRole = BaseRole::make($role);
                }
            } else {
                $newRole = $role;
            }

            $newEncryptedKey = $newRole::getDbKey();

            $model = Roster::where([
                'assignable_type' => $user->getMorphClass(),
                'assignable_id' => $user->getKey(),
                'roleable_id' => $target->getKey(),
                'roleable_type' => $target->getMorphClass(),
            ])->lockForUpdate()->first();

            if ($model) {
                $oldEncryptedKey = $model->getRoleDBKey();
                $oldRole = RoleFactory::tryMake($oldEncryptedKey);

                $model->role_key = $newEncryptedKey;
                $model->save();

                if ($oldRole) {
                    RoleChanged::dispatch($user, $target, $oldRole, $newRole);
                }
            } else {
                $this->assign($user, $target, $newRole);

                return;
            }
        });

        $this->clearCache($target, $user);
    }

    public function getParticipantsHasRole(RoleableEntity $target, string|RoleContract $role): Collection
    {
        if (is_string($role)) {
            $this->ensureRoleExists($role);
            try {
                $roleInstance = RoleFactory::make($role);
            } catch (InvalidArgumentException $e) {
                $roleInstance = BaseRole::make($role);
            }
        } else {
            $roleInstance = $role;
        }

        $encryptedKey = $roleInstance::getDbKey();

        return Roster::where([
            'roleable_type' => $target->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'role_key' => $encryptedKey,
        ])->with('assignable')->get()->pluck('assignable');
    }

    public function getAssignedEntitiesByKeysByType(AssignableEntity $target, array $keys, string $type): Collection
    {
        return Roster::query()->where([
            'assignable_type' => $target->getMorphClass(),
            'assignable_id' => $target->getKey(),
            'roleable_type' => $type,
        ])
            ->with('roleable')
            ->whereIn('roleable_id', $keys)
            ->get()
            ->pluck('roleable');
    }

    public function getAssignedEntitiesByType(AssignableEntity $entity, string $type): Collection
    {
        $cacheKey = $this->generateAssignedEntitiesCacheKey($entity, $type);
        if (config('porter.should_cache')) {
            return Cache::remember($cacheKey, now()->addHour(), fn () => Roster::where([
                'roleable_type' => $type,
                'assignable_type' => $entity->getMorphClass(),
                'assignable_id' => $entity->getKey(),
            ])
                ->with('roleable')
                ->get()
                ->pluck('roleable'));
        }

        return Roster::where([
            'roleable_type' => $type,
            'assignable_type' => $entity->getMorphClass(),
            'assignable_id' => $entity->getKey(),
        ])
            ->with('roleable')
            ->get()->pluck('roleable');
    }

    public function getParticipantsWithRoles(RoleableEntity $target): Collection
    {
        if (config('porter.should_cache')) {
            return Cache::remember($this->generateParticipantsCacheKey($target), now()->addHour(), function () use ($target) {
                return Roster::where([
                    'roleable_id' => $target->getKey(),
                    'roleable_type' => $target->getMorphClass(),
                ])
                    ->with('assignable')
                    ->get();
            });
        }

        return Roster::where([
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])
            ->with('assignable')
            ->get();
    }

    public function hasRoleOn(AssignableEntity $user, RoleableEntity $target, string|RoleContract $role): bool
    {
        if (is_string($role)) {
            $roleInstance = RoleFactory::tryMake($role);
            if (! $roleInstance) {
                $roleInstance = BaseRole::tryMake($role);
            }
            if (! $roleInstance) {
                return false;
            }
        } else {
            $roleInstance = $role;
        }

        $encryptedKey = $roleInstance::getDbKey();

        if (config('porter.should_cache')) {
            $cacheKey = $this->generateRoleCheckCacheKey($user, $target, $roleInstance);

            return Cache::remember($cacheKey, now()->addMinutes(30), fn () => $this->performRoleCheck($user, $target, $encryptedKey));
        }

        return $this->performRoleCheck($user, $target, $encryptedKey);
    }

    public function hasAnyRoleOn(AssignableEntity $user, RoleableEntity $target): bool
    {
        return Roster::where([
            'assignable_id' => $user->getKey(),
            'assignable_type' => $user->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->exists();
    }

    public function getRoleOn(AssignableEntity $user, RoleableEntity $target): ?RoleContract
    {
        $encryptedKey = Roster::where([
            'assignable_id' => $user->getKey(),
            'assignable_type' => $user->getMorphClass(),
            'roleable_type' => $target->getMorphClass(),
            'roleable_id' => $target->getKey(),
        ])->first()?->role_key::getDbKey();

        if ($encryptedKey) {
            $role = RoleFactory::tryMake($encryptedKey);

            return $role;
        }

        return null;
    }

    public function isAtLeastOn(AssignableEntity $user, RoleContract $role, RoleableEntity $target): bool
    {
        $userRole = $this->getRoleOn($user, $target);

        if (! $userRole) {
            return false;
        }

        return $userRole->isAtLeast($role);
    }

    public function ensureRoleExists(string $roleIdentifier): void
    {
        try {
            RoleFactory::make($roleIdentifier);
        } catch (InvalidArgumentException $e) {
            try {
                BaseRole::make($roleIdentifier);
            } catch (InvalidArgumentException $e2) {
                throw new DomainException("Role '{$roleIdentifier}' does not exist.", 0, $e2);
            }
        }
    }

    public function clearCache(RoleableEntity $target, ?AssignableEntity $user = null): void
    {
        // Forget participants cache
        Cache::forget($this->generateParticipantsCacheKey($target));

        // Forget assignable entity cache if user provided
        if ($user) {
            Cache::forget($this->generateAssignedEntitiesCacheKey($user, $target->getMorphClass()));
        }
    }

    public function generateParticipantsCacheKey(RoleableEntity $target): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        return "{$prefix}:participants:{$target->getMorphClass()}:{$target->getKey()}";
    }

    public function bulkClearCache(Collection $targets): void
    {
        $targets->each(function (RoleableEntity $target) {
            return $this->clearCache($target);
        });
    }

    private function removeWithinTransaction(AssignableEntity $user, RoleableEntity $target): void
    {
        $assignments = Roster::where([
            'assignable_type' => $user->getMorphClass(),
            'assignable_id' => $user->getKey(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->lockForUpdate()->get();

        Roster::where([
            'assignable_type' => $user->getMorphClass(),
            'assignable_id' => $user->getKey(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
        ])->delete();

        foreach ($assignments as $assignment) {
            $encryptedKey = $assignment->getRoleDBKey();
            $role = RoleFactory::tryMake($encryptedKey);
            if ($role) {
                RoleRemoved::dispatch($user, $target, $role);
            }
        }
    }

    private function performRoleCheck(AssignableEntity $user, RoleableEntity $target, string $encryptedKey): bool
    {
        return Roster::where([
            'assignable_id' => $user->getKey(),
            'assignable_type' => $user->getMorphClass(),
            'roleable_id' => $target->getKey(),
            'roleable_type' => $target->getMorphClass(),
            'role_key' => $encryptedKey,
        ])->exists();
    }

    private function generateRoleCheckCacheKey(AssignableEntity $user, RoleableEntity $target, RoleContract $role): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        return "{$prefix}:role_check:{$user->getMorphClass()}:{$user->getKey()}:{$target->getMorphClass()}:{$target->getKey()}:{$role->getName()}";
    }

    private function clearAssignableEntityCache(AssignableEntity $user, string $type): void
    {
        Cache::forget($this->generateAssignedEntitiesCacheKey($user, $type));
    }

    private function generateAssignedEntitiesCacheKey(AssignableEntity $target, string $type): string
    {
        $prefix = config('porter.cache.key_prefix', 'porter');

        return "{$prefix}:{$target->getMorphClass()}:{$target->getKey()}_{$type}_entities";
    }
}
