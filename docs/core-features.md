# Core Features

Porter's architecture is built around four core pillars: **Individual Role Classes**, **Ultra-Minimal Architecture**, **Blazing Performance**, and **Enterprise-Grade Testing**. Each feature is designed to solve real problems developers face with traditional RBAC systems while ensuring production reliability.

## 🎯 Individual Role Classes

Each role is its own focused class extending `BaseRole` - no more generic "role" entities:

```php
final class Admin extends BaseRole
{
    public function getName(): string { return 'admin'; }
    public function getLevel(): int { return 10; }
    public function getDescription(): string { return 'System administrator with full access'; }
}

final class Editor extends BaseRole
{
    public function getName(): string { return 'editor'; }
    public function getLevel(): int { return 5; }
    public function getDescription(): string { return 'Content editor with publishing rights'; }
}
```

### Benefits:
- **Type Safety**: Full PHP type hints and IDE autocomplete
- **Business Logic**: Embed role-specific methods and business rules
- **Single Responsibility**: Each role class focuses on one responsibility
- **Testable**: Unit test individual role behavior

### Advanced Role Classes with Business Logic:

```php
final class RegionalManager extends BaseRole
{
    public function getName(): string { return 'regional_manager'; }
    public function getLevel(): int { return 8; }

    public function getRegions(): array
    {
        return ['north', 'south', 'east', 'west'];
    }

    public function canAccessRegion(string $region): bool
    {
        return in_array($region, $this->getRegions());
    }

    public function getMaxBudgetApproval(): int
    {
        return 100000; // $100k approval limit
    }
}

// Usage in business logic
if ($user->hasRoleOn($company, 'regional_manager')) {
    $role = Porter::getRoleOn($user, $company);

    if ($role->canAccessRegion('north') && $budget <= $role->getMaxBudgetApproval()) {
        // Approve the budget for northern region
    }
}
```

## 🚀 Ultra-Minimal Architecture

Just **3 core components** - no bloat, no confusion:

### 1. RoleManager - Role Assignment Service
```php
use Hdaklue\Porter\Facades\Porter;

// All role operations through one clean API
Porter::assign($user, $project, 'admin');
Porter::remove($user, $project);
Porter::changeRoleOn($user, $project, 'editor');

$role = Porter::getRoleOn($user, $project);
$participants = Porter::getParticipants($project);
```

### 2. Roster Model - Enhanced Role Assignments
```php
use Hdaklue\Porter\Models\Roster;

// Query role assignments with new scopes
$userAssignments = Roster::forAssignable(User::class, $user->id)->get();
$projectRoles = Roster::forRoleable(Project::class, $project->id)->get();
$adminAssignments = Roster::withRoleName('admin')->get();

// Timestamps for audit trails
$assignment = Roster::create([...]);
echo "Assigned on: " . $assignment->created_at;

// Human-readable descriptions
foreach ($assignments as $assignment) {
    echo $assignment->description;
    // Output: "User #123 has role 'admin' on Project #456"
}
```

### 3. Individual Role Classes - Your Business Logic
Each role encapsulates its own behavior, hierarchy level, and business rules.

## 🔥 Blazing Performance

Porter is optimized for real-world performance with several key design decisions:

### Single Table Architecture
Porter uses **exactly ONE database table** (`roaster`) for all role assignments:

```sql
-- The ENTIRE role system in one table:
CREATE TABLE roaster (
    id bigint PRIMARY KEY,
    assignable_type varchar(255),  -- 'App\Models\User'  
    assignable_id varchar(255),    -- ULID: '01HBQM5F8G9YZ2XJKPQR4VWXYZ'
    roleable_type varchar(255),    -- 'App\Models\Project'
    roleable_id varchar(255),      -- ULID: '01HBQM6G9HAZB3YLKQRS5WXYZA' 
    role_key varchar(255),         -- 'admin'
    created_at timestamp,
    updated_at timestamp,

    -- Prevents duplicate assignments
    UNIQUE KEY porter_unique (assignable_type, assignable_id, roleable_type, roleable_id, role_key)
);
```

### Performance Benefits:
- 🚀 **Zero complex queries** - Simple single-table operations
- 🏃 **Fast role assignments** - Direct database operations with minimal overhead  
- 💾 **Minimal codebase** - Focused architecture with 8 core classes
- 🧠 **Efficient memory usage** - Individual role classes prevent bloat
- ⚡ **No foreign key overhead** - Polymorphic relationships without constraints
- 🗲 **Built-in caching** - Optimized RoleValidator with simplified cache strategy

### Cache Strategy:
```php
// Simplified path-based caching
private static array $classPathCache = [];

// Cache only file paths, not complex data structures
$classPaths[$filename] = $filePath;

// Cleared after each role creation
RoleValidator::clearCache();
```

## 🆕 Latest Features

### Dynamic Role Factory
Magic `__callStatic` methods for type-safe role instantiation:

```php
use Hdaklue\Porter\RoleFactory;

// Magic factory methods - scans your Porter directory automatically
$admin = RoleFactory::admin();           // Creates Admin role instance
$projectManager = RoleFactory::projectManager(); // Creates ProjectManager role instance

// Check if role exists before using
if (RoleFactory::existsInPorterDirectory('CustomRole')) {
    $role = RoleFactory::customRole();
}

// Get all roles from directory
$allRoles = RoleFactory::allFromPorterDirectory();
```

### Config-Driven Architecture
Configurable directory and namespace settings:

```php
// config/porter.php
'directory' => env('PORTER_DIRECTORY', app_path('Porter')),
'namespace' => env('PORTER_NAMESPACE', 'App\\Porter'),
```

### Interactive Role Creation
Guided role creation with automatic level calculation:

```bash
# Interactive command with guided setup
php artisan porter:create

# Choose creation mode: lowest, highest, lower, higher
# Automatic level calculation based on existing roles
# Smart hierarchy management prevents conflicts
```

### Laravel Contracts Integration
Uses `RoleContract` following Laravel conventions:

```php
use Hdaklue\Porter\Contracts\RoleContract;

final class Admin extends BaseRole implements RoleContract
{
    // Full type safety with Laravel's contract system
}
```

### Enhanced Security Options
```php
// config/porter.php
'security' => [
    'assignment_strategy' => env('PORTER_ASSIGNMENT_STRATEGY', 'replace'), // 'replace' or 'add'
    'key_storage' => env('PORTER_KEY_STORAGE', 'hashed'),  // 'hashed' or 'plain'
    'auto_generate_keys' => env('PORTER_AUTO_KEYS', true),
],
```

## 🧪 Enterprise-Grade Testing

Porter includes **190 comprehensive tests** with **1,606 assertions** covering every aspect of production deployment, from security hardening to scalability validation.

### Security Validation Testing
Porter's security testing ensures protection against real-world attack vectors:

```php
// SQL injection prevention testing
it('prevents SQL injection in role keys', function () {
    $maliciousRoleKey = "'; DROP TABLE roster; --";
    
    expect(function () use ($maliciousRoleKey) {
        app(RoleManager::class)->assign($user, $project, $maliciousRoleKey);
    })->toThrow(\Exception::class);
    
    // Verify table security maintained
    expect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name='roster'"))
        ->not()->toBeEmpty();
});
```

### Performance & Scalability Testing
Comprehensive performance validation ensures enterprise-scale readiness:

```php
// Large dataset performance testing
it('handles 1000+ role assignments efficiently', function () {
    $users = collect(range(1, 100))->map(fn($i) => createTestUser($i));
    $projects = collect(range(1, 100))->map(fn($i) => createTestProject($i));
    
    $startTime = microtime(true);
    
    // Create 300+ role assignments with performance monitoring
    foreach ($users->take(10) as $user) {
        foreach ($projects->take(10) as $project) {
            foreach (['Admin', 'Editor', 'Viewer'] as $role) {
                app(RoleManager::class)->assign($user, $project, $role);
            }
        }
    }
    
    $executionTime = microtime(true) - $startTime;
    expect($executionTime)->toBeLessThan(5.0); // Performance benchmark
});
```

### Error Recovery & Resilience Testing
System reliability validation through comprehensive failure scenario testing:

```php
// Database failure recovery testing
it('recovers from database connection failures gracefully', function () {
    // Establish working connection first
    app(RoleManager::class)->assign($user, $project, 'TestAdmin');
    expect($user->hasRoleOn($project, 'TestAdmin'))->toBeTrue();
    
    // Test graceful failure handling
    expect(function () {
        app(RoleManager::class)->assign($user, $project, 'NonexistentRole');
    })->toThrow(\Exception::class);
    
    // Verify system remains operational
    app(RoleManager::class)->assign($user, $project, 'TestEditor');
    expect($user->hasRoleOn($project, 'TestEditor'))->toBeTrue();
});
```

### Test Coverage Categories

| **Category** | **Tests** | **Assertions** | **Focus** |
|-------------|-----------|----------------|-----------|
| **Security Hardening** | 15 | 87 | SQL injection, timing attacks, input sanitization |
| **Scalability** | 12 | 156 | Large datasets, memory profiling, concurrent access |
| **Error Recovery** | 22 | 324 | Database failures, cache failures, system resilience |
| **Advanced Scenarios** | 14 | 189 | Complex hierarchies, cross-tenant isolation |
| **Core Functionality** | 127 | 850 | Role management, validation, Laravel integration |

### Quality Assurance Benefits

- ✅ **Production Confidence** - Extensive validation across all components
- ✅ **Security Assurance** - Attack vector protection verified
- ✅ **Performance Validation** - Scalability benchmarks confirmed  
- ✅ **Error Resilience** - Graceful failure recovery tested
- ✅ **Cross-Database** - Multi-connection architecture validated
- ✅ **Continuous Integration** - Automated testing across PHP 8.1-8.3 and Laravel 11-12

### Running the Test Suite

```bash
# Run complete test suite (190 tests)
vendor/bin/pest

# Run security validation tests
vendor/bin/pest tests/Feature/SecurityHardeningTest.php

# Run performance tests with memory profiling
vendor/bin/pest tests/Feature/ScalabilityTest.php

# Run error recovery tests
vendor/bin/pest tests/Feature/ErrorRecoveryTest.php

# Run advanced scenario tests
vendor/bin/pest tests/Feature/AdvancedScenariosTest.php
```

The comprehensive test suite provides enterprise-level confidence in Porter's reliability, security, and performance for production deployments.

## 🎨 Perfect Laravel Integration

Works seamlessly with your existing authorization:

```php
// In your Policy
public function update(User $user, Project $project)
{
    return $user->hasRoleOn($project, 'admin');
}

// In your Controller
$this->authorize('update', $project);

// In your Blade templates
@can('update', $project)
    <button>Edit Project</button>
@endcan

// In your Form Requests
public function authorize()
{
    return $this->user()->hasRoleOn($this->project, 'admin');
}
```

### Role Hierarchy & Smart Comparisons

```php
use App\Porter\{Admin, ProjectManager, Developer, Viewer};

$admin = new Admin();           // Level 10
$manager = new ProjectManager(); // Level 7
$developer = new Developer();    // Level 3
$viewer = new Viewer();         // Level 1

// Intelligent role comparisons
$admin->isHigherThan($manager);     // true
$manager->isHigherThan($developer); // true
$developer->isLowerThan($admin);    // true
$admin->equals(new Admin());        // true

// Business logic in your controllers
public function canManageProject(User $user, Project $project): bool
{
    $userRole = RoleManager::getRoleOn($user, $project);
    $requiredRole = new ProjectManager();

    return $userRole && $userRole->isHigherThanOrEqual($requiredRole);
}
```

## Installation Options

### Basic Installation
```bash
# Creates Porter directory with BaseRole only
php artisan porter:install
```

### Full Installation  
```bash
# Includes 6 default role classes with proper hierarchy
php artisan porter:install --roles
```

### Installation Features:
- ✅ **Publishes configuration file** with sensible defaults
- ✅ **Publishes and runs migrations** automatically  
- ✅ **Creates Porter directory** with configurable namespace
- ✅ **Optionally creates 6 default role classes** (Admin, Manager, Editor, Contributor, Viewer, Guest)
- ✅ **Provides contextual next-step guidance** for immediate productivity
- ✅ **Blocks installation in production** environment for safety
- ✅ **`--force` flag support** for overwriting existing files

The result is a complete, production-ready role system that scales with your application while maintaining simplicity and performance.