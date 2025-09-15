# Porter Multitenancy Guide

Porter includes comprehensive **optional multitenancy support** designed for SaaS applications and enterprise multi-tenant architectures. When enabled, Porter provides tenant-aware role assignments with integrity validation, flexible tenant patterns, and advanced self-reference capabilities.

## Table of Contents

- [Configuration](#configuration)
- [Core Concepts](#core-concepts)
- [Tenant Patterns](#tenant-patterns)
- [Self-Reference Support](#self-reference-support)
- [Tenant Integrity Validation](#tenant-integrity-validation)
- [Cache & Performance](#cache--performance)
- [Database Migration](#database-migration)
- [API Reference](#api-reference)
- [Testing Multitenancy](#testing-multitenancy)
- [Troubleshooting](#troubleshooting)

---

## Configuration

Enable multitenancy by updating your `config/porter.php` file:

```php
'multitenancy' => [
    // Enable/disable multitenancy features
    'enabled' => env('PORTER_MULTITENANCY_ENABLED', false),
    
    // Tenant key type for database storage
    'tenant_key_type' => env('PORTER_TENANT_KEY_TYPE', 'string'), // 'string', 'uuid', 'ulid', 'integer'
    
    // Database column name for tenant identifier
    'tenant_column' => env('PORTER_TENANT_COLUMN', 'tenant_id'),
    
    // Automatically scope queries by tenant
    'auto_scope' => env('PORTER_AUTO_SCOPE', true),
    
    // Enable tenant-specific caching
    'cache_per_tenant' => env('PORTER_CACHE_PER_TENANT', true),
],
```

### Environment Configuration

```bash
# .env file configuration
PORTER_MULTITENANCY_ENABLED=true
PORTER_TENANT_KEY_TYPE=string        # or uuid, ulid, integer
PORTER_TENANT_COLUMN=tenant_id       # Custom column name if needed
PORTER_AUTO_SCOPE=true               # Auto-scope queries by tenant
PORTER_CACHE_PER_TENANT=true         # Tenant-specific cache isolation
```

---

## Core Concepts

### Tenant-Aware Entities

Porter supports two types of tenant-aware entities:

1. **Assignable Entities** (those who receive roles)
2. **Roleable Entities** (those on which roles are assigned)

### Tenant Traits

Porter provides specialized traits for different tenant scenarios:

#### For Assignable Entities (Users, Teams)
```php
use Hdaklue\Porter\Multitenancy\Concerns\HasPorterTenant;

class User extends Authenticatable implements AssignableEntity
{
    use CanBeAssignedToEntity;
    use HasPorterTenant;

    // Implement tenant key resolution
    public function getCurrentTenantKey(): ?string
    {
        return $this->tenant_id; // or your tenant field
    }
}
```

#### For Roleable Entities (Projects, Organizations)
```php
use Hdaklue\Porter\Multitenancy\Concerns\HasPorterTenantScope;

class Project extends Model implements RoleableEntity
{
    use ReceivesRoleAssignments;
    use HasPorterTenantScope;

    // Implement tenant key resolution
    public function getPorterTenantKey(): ?string
    {
        return $this->organization_id; // or your tenant field
    }
}
```

#### For Tenant Entities Themselves
```php
use Hdaklue\Porter\Multitenancy\Concerns\IsPorterTenant;
use Hdaklue\Porter\Multitenancy\Contracts\PorterTenantContract;

class Organization extends Model implements PorterTenantContract
{
    use IsPorterTenant;

    // Automatically implements getPorterTenantKey() returning $this->getKey()
}
```

---

## Tenant Patterns

### Pattern 1: Standard Multi-Tenant SaaS

Users belong to organizations and have roles on organization projects:

```php
class User extends Authenticatable
{
    use HasPorterTenant;
    
    public function getCurrentTenantKey(): ?string
    {
        return $this->organization_id;
    }
}

class Project extends Model
{
    use HasPorterTenantScope;
    
    public function getPorterTenantKey(): ?string
    {
        return $this->organization_id;
    }
}

// Usage
Porter::assign($user, $project, 'manager'); // Validates same organization
```

### Pattern 2: Team-Based Access Control

Users can have roles on teams within their tenant:

```php
class User extends Authenticatable
{
    use HasPorterTenant;
    
    public function getCurrentTenantKey(): ?string
    {
        return $this->company_id;
    }
}

class Team extends Model
{
    use HasPorterTenantScope;
    
    public function getPorterTenantKey(): ?string
    {
        return $this->company_id;
    }
}

// Teams can have roles on projects
class Project extends Model
{
    use HasPorterTenantScope;
    
    public function getPorterTenantKey(): ?string
    {
        return $this->company_id;
    }
}

// Usage
Porter::assign($user, $team, 'member');        // User joins team
Porter::assign($team, $project, 'collaborator'); // Team works on project
```

### Pattern 3: Hierarchical Tenancy

Multi-level tenant hierarchies with inheritance:

```php
class User extends Authenticatable
{
    use HasPorterTenant;
    
    public function getCurrentTenantKey(): ?string
    {
        // Could return department, division, or company based on context
        return $this->getCurrentContext();
    }
    
    private function getCurrentContext(): string
    {
        // Business logic to determine current tenant context
        return session('current_tenant') ?? $this->primary_tenant_id;
    }
}
```

---

## Self-Reference Support

Porter supports scenarios where tenant entities can be roleables (users can have roles on their own tenant):

### Tenant as Roleable

```php
class Organization extends Model implements PorterTenantContract
{
    use IsPorterTenant;
    use ReceivesRoleAssignments; // Can receive role assignments
    
    // IsPorterTenant automatically provides:
    // public function getPorterTenantKey(): string
    // {
    //     return (string) $this->getKey();
    // }
}

class User extends Authenticatable
{
    use HasPorterTenant;
    
    public function getCurrentTenantKey(): ?string
    {
        return $this->organization_id;
    }
}

// Users can have roles on their organization
Porter::assign($user, $organization, 'owner');  // Self-reference allowed
Porter::assign($user, $organization, 'admin');  // Multiple roles supported
```

### Special Validation for Self-Reference

Porter automatically handles self-reference validation:

```php
// When tenant entity is the roleable, Porter allows the assignment
// even though the tenant "belongs to itself"
$organization = Organization::find(123);
$user = User::where('organization_id', 123)->first();

// This works - user can have role on their own organization
Porter::assign($user, $organization, 'owner');

// This fails - user from different organization cannot have role
$otherUser = User::where('organization_id', 456)->first();
Porter::assign($otherUser, $organization, 'viewer'); // TenantIntegrityException
```

---

## Tenant Integrity Validation

Porter enforces strict tenant integrity to prevent unauthorized cross-tenant access:

### Validation Rules

1. **Same Tenant Required**: Assignable and roleable must belong to same tenant
2. **Null Tenant Handling**: Both entities must have null tenant, or both must have same non-null tenant
3. **Self-Reference Exception**: When roleable implements `PorterTenantContract`, special validation applies

### Validation Examples

```php
// ✅ Valid: Same tenant
$user = User::create(['tenant_id' => 'org_123']);
$project = Project::create(['tenant_id' => 'org_123']);
Porter::assign($user, $project, 'admin'); // Success

// ❌ Invalid: Different tenants
$user = User::create(['tenant_id' => 'org_123']);
$project = Project::create(['tenant_id' => 'org_456']);
Porter::assign($user, $project, 'admin'); // TenantIntegrityException

// ✅ Valid: Both null tenants (global scope)
$user = User::create(['tenant_id' => null]);
$project = Project::create(['tenant_id' => null]);
Porter::assign($user, $project, 'admin'); // Success

// ✅ Valid: Self-reference with tenant entity
$organization = Organization::find(123);
$user = User::create(['tenant_id' => '123']);
Porter::assign($user, $organization, 'owner'); // Success (special case)
```

### Custom Validation Messages

```php
use Hdaklue\Porter\Multitenancy\Exceptions\TenantIntegrityException;

try {
    Porter::assign($user, $project, 'admin');
} catch (TenantIntegrityException $e) {
    // Handle tenant integrity violations
    switch ($e->getCode()) {
        case TenantIntegrityException::ASSIGNABLE_NO_TENANT:
            // User has no tenant context
            break;
        case TenantIntegrityException::ROLEABLE_NO_TENANT:
            // Entity has no tenant context
            break;
        case TenantIntegrityException::TENANT_MISMATCH:
            // Tenant contexts don't match
            break;
    }
}
```

---

## Cache & Performance

### Tenant-Specific Caching

When `cache_per_tenant` is enabled, Porter creates tenant-isolated cache keys:

```php
// Without multitenancy
'cache_key' => 'porter:user:123:project:456:admin'

// With multitenancy
'cache_key' => 'porter:tenant:org_123:user:123:project:456:admin'
```

### Cache Invalidation

Porter automatically handles tenant-aware cache invalidation:

```php
// When role is assigned, only tenant-specific cache is cleared
Porter::assign($user, $project, 'admin');
// Clears: porter:tenant:org_123:* (only this tenant's cache)

// Bulk tenant cleanup clears all tenant cache
Porter::destroyTenantRoles('org_123');
// Clears: porter:tenant:org_123:* (all cache for tenant)
```

### Performance Optimizations

1. **Tenant Scoping**: Automatic query scoping reduces result sets
2. **Isolated Caching**: Prevents cache pollution between tenants
3. **Batch Operations**: Efficient bulk operations for tenant cleanup
4. **Query Optimization**: Tenant-aware indexes improve performance

---

## Database Migration

Porter includes a conditional migration that only runs when multitenancy is enabled:

### Migration Features

```php
// Migration runs conditionally
if (config('porter.multitenancy.enabled')) {
    Schema::table('roster', function (Blueprint $table) {
        // Adds tenant column based on configuration
        $tenantKeyType = config('porter.multitenancy.tenant_key_type', 'string');
        $tenantColumn = config('porter.multitenancy.tenant_column', 'tenant_id');
        
        match ($tenantKeyType) {
            'integer' => $table->unsignedBigInteger($tenantColumn)->nullable(),
            'uuid' => $table->uuid($tenantColumn)->nullable(),
            'ulid' => $table->ulid($tenantColumn)->nullable(),
            default => $table->string($tenantColumn)->nullable(),
        };
        
        // Performance index
        $table->index([$tenantColumn], 'porter_tenant_idx');
    });
}
```

### Running Migration

```bash
# Standard migration (respects configuration)
php artisan migrate

# Force migration with specific database
php artisan migrate --database=tenant_db
```

---

## API Reference

### RoleManager Methods

#### `destroyTenantRoles(string $tenantKey): int`

Efficiently removes all role assignments for a specific tenant:

```php
// Remove all roles for tenant
$deletedCount = Porter::destroyTenantRoles('org_123');

// Returns number of assignments deleted
echo "Removed {$deletedCount} role assignments";

// Automatically clears tenant-specific cache
```

#### `validateTenantIntegrity()` (Internal)

Automatically called during role assignment to ensure tenant integrity:

```php
// Called automatically in assign() method
// Validates assignable and roleable belong to same tenant
// Handles special cases for self-reference scenarios
```

#### `resolveTenantIdForAssignment()` (Internal)

Determines the tenant context for a role assignment:

```php
// Resolves tenant from assignable entity
// Handles self-reference cases where roleable is tenant
// Returns null for global (non-tenant) assignments
```

### Query Scopes

#### TenantAware Trait Scopes

```php
// Scope to specific tenant
$projects = Project::forTenant('org_123')->get();

// Exclude tenant filtering  
$allProjects = Project::withoutTenant()->get();

// Get all tenants (removes any existing tenant scope)
$globalProjects = Project::forAllTenants()->get();
```

### Tenant Helper Methods

```php
// Check if entity belongs to tenant
$belongsToTenant = $project->belongsToTenant('org_123'); // boolean

// Get entity's tenant
$tenantId = $project->getTenantId(); // string|null

// Set entity's tenant
$project->setTenantId('org_456');
```

---

## Testing Multitenancy

Porter includes comprehensive multitenancy tests. Here's how to test your implementation:

### Test Setup

```php
use Hdaklue\Porter\Tests\Fixtures\{TestUser, TestProject};

beforeEach(function () {
    // Enable multitenancy for tests
    config([
        'porter.multitenancy.enabled' => true,
        'porter.multitenancy.tenant_key_type' => 'string',
        'porter.multitenancy.auto_scope' => true,
    ]);
});
```

### Test Scenarios

```php
test('tenant integrity validation works', function () {
    $user = TestUser::create(['tenant_id' => 'tenant_123']);
    $project = TestProject::create(['tenant_id' => 'tenant_456']);
    
    expect(fn() => Porter::assign($user, $project, 'admin'))
        ->toThrow(TenantIntegrityException::class);
});

test('self-reference scenarios work', function () {
    $organization = Organization::create(['name' => 'Test Org']);
    $user = TestUser::create(['tenant_id' => (string) $organization->id]);
    
    expect(fn() => Porter::assign($user, $organization, 'owner'))
        ->not->toThrow();
});

test('bulk tenant cleanup works', function () {
    // Create multiple assignments for tenant
    Porter::assign($user1, $project1, 'admin');
    Porter::assign($user2, $project2, 'editor');
    
    $deletedCount = Porter::destroyTenantRoles('tenant_123');
    
    expect($deletedCount)->toBe(2);
    expect(Roster::where('tenant_id', 'tenant_123')->count())->toBe(0);
});
```

---

## Troubleshooting

### Common Issues

**1. TenantIntegrityException on Valid Assignments**

Check that both entities implement the correct tenant resolution methods:

```php
// Assignable entity must implement getCurrentTenantKey()
public function getCurrentTenantKey(): ?string
{
    return $this->tenant_id; // Make sure this field exists and is populated
}

// Roleable entity must implement getPorterTenantKey()  
public function getPorterTenantKey(): ?string
{
    return $this->organization_id; // Make sure this field exists and is populated
}
```

**2. Migration Not Running**

Ensure multitenancy is enabled in config:

```php
// config/porter.php
'multitenancy' => [
    'enabled' => true, // Must be true for migration to run
],
```

**3. Cache Issues**

Clear Porter cache after enabling multitenancy:

```bash
php artisan cache:clear
php artisan config:clear
```

**4. Self-Reference Not Working**

Ensure tenant entity implements `PorterTenantContract`:

```php
class Organization extends Model implements PorterTenantContract
{
    use IsPorterTenant; // Provides getPorterTenantKey() implementation
}
```

### Debug Commands

```php
// Check multitenancy configuration
php artisan tinker
> config('porter.multitenancy')

// Verify tenant resolution
> $user->getCurrentTenantKey()
> $project->getPorterTenantKey()

// Check roster table structure
> Schema::getColumnListing('roster')
```

### Performance Monitoring

```php
// Enable query logging to monitor tenant-scoped queries
DB::enableQueryLog();

// Execute tenant-aware operations
$projects = Project::forTenant('org_123')->get();

// Review queries
dd(DB::getQueryLog());
```

---

## Advanced Patterns

### Dynamic Tenant Switching

```php
class User extends Authenticatable
{
    use HasPorterTenant;
    
    protected $currentTenantOverride = null;
    
    public function getCurrentTenantKey(): ?string
    {
        return $this->currentTenantOverride ?? $this->primary_tenant_id;
    }
    
    public function switchTenant(string $tenantId): void
    {
        $this->currentTenantOverride = $tenantId;
        
        // Clear user-specific cache when switching
        Porter::clearCacheForAssignable($this);
    }
}
```

### Tenant Hierarchy Support

```php
class TenantHierarchy
{
    public static function getAccessibleTenants(User $user): array
    {
        $userTenant = $user->getCurrentTenantKey();
        
        // Return tenant and all sub-tenants user can access
        return array_merge(
            [$userTenant],
            static::getSubTenants($userTenant)
        );
    }
    
    public static function scopeToAccessibleTenants(Builder $query, User $user): Builder
    {
        $accessibleTenants = static::getAccessibleTenants($user);
        
        return $query->whereIn('tenant_id', $accessibleTenants);
    }
}
```

### Cross-Tenant Role Reports

```php
class TenantRoleReport
{
    public static function getUserRolesAcrossTenants(User $user): Collection
    {
        // Temporarily disable tenant scoping
        return Roster::withoutTenant()
            ->forAssignable(get_class($user), $user->getKey())
            ->with(['roleable'])
            ->get()
            ->groupBy('tenant_id');
    }
}
```

---

Porter's multitenancy system provides enterprise-grade tenant isolation while maintaining the package's signature simplicity and performance. The flexible architecture supports various tenant patterns from simple SaaS applications to complex enterprise hierarchies.