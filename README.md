# Porter - Ultra-Minimal Laravel Access Control

**Your application's trusted doorkeeper** 🚪

A lightweight, blazing-fast Laravel access control package that treats roles as what they truly are: **domain business logic**, not database abstractions. Built for developers who value simplicity, performance, and clean architecture.

**Porter's Core Concept**: Any model can be **Assignable** (users, teams, departments), any model can be **Roleable** (projects, organizations, documents), and the **Roster** defines the access control relationship between them. This flexibility lets you model complex business scenarios with simple, expressive code.

**Perfect for**: [Team collaboration](#team-collaboration-platform), [SaaS feature consumption](#saas-feature-consumption), [document management](#document-management-system), [project access control](#project-management-system), [multi-tenant applications](#multi-tenant-application), and [enterprise hierarchies](#enterprise-hierarchy-management).

> "Current RBAC systems were made for CMSs."

## 🎥 Video Demos Needed

Porter is seeking a co-maintainer to create video demos and tutorials showcasing the package features and usage. If you're interested in creating educational content for this Laravel package, please contact hassan@daklue.com

--- 

## Table of Contents

- [Why Porter?](#why-porter)
- [Roadmap & Community Input](#roadmap--community-input)
- [Core Features](#core-features) • **[Complete Guide →](docs/core-features.md)**
- [Suggested Usage](#suggested-usage) • **[Complete Guide →](docs/suggested-usage.md)**
- [Installation](#installation)
- [Advanced Features](#advanced-features)
- [Configuration](#configuration)
- [Laravel Integration](#laravel-integration) • **[Complete Guide →](docs/laravel-integration.md)**
- [Migration Strategy](#migration-strategy)
- [Performance](#performance)
- [CLI Commands](#cli-commands)
- [Testing](#testing)
- [Requirements](#requirements)
- [Contributing](#contributing)
- [License](#license)

--- 

## Why Porter?

> *"Roles are business logic, not database magic."*

Porter was born from the frustration of dealing with bloated RBAC packages that turn simple role assignments into complex database gymnastics. As a **fresh package** entering the Laravel ecosystem, Porter aims to solve real problems that developers face daily. I believe in providing a solution that is both powerful and elegant, convincing the community that there's a better way to handle role management.

### The Problem with Existing Solutions

Most RBAC packages are:
- **Over-engineered** - 30+ classes for simple role assignments
- **Database-heavy** - Complex joins and foreign key nightmares
- **Performance-blind** - Slow queries that don't scale
- **Generic** - One-size-fits-all approaches that fit no one

### Porter's Approach

Porter treats roles as **business assignments** - contextual relationships between users and entities, not generic database records. Each role assignment carries business logic and domain knowledge.

### Porter vs Database-Heavy Approaches

Common question: *"Why not use traditional database-based access control?"*

| Feature | Database-Heavy Systems | Porter Access Control |
|---------|---------------------|--------|
| **Assignment Model** | Fixed user-permission mappings | Flexible Assignable-Roleable-Roster pattern |
| **Entity Support** | Limited to users and roles | Any model as Assignable or Roleable |
| **Role Concept** | Generic database records | Business assignments with context |
| **Assignment Logic** | Database foreign keys | PHP class methods with business rules |
| **Entity Context** | Global permissions | Entity-specific assignments |
| **Type Safety** | String-based | Full PHP type safety |
| **Business Logic** | Scattered across codebase | Encapsulated in role classes |
| **IDE Support** | Limited | Full autocomplete |
| **Performance** | Multiple DB queries | Single table, memory checks |

**Use Traditional Systems if:** You need complex global permission matrices  
**Use Porter Access Control if:** You need flexible entity-specific assignments with type safety and simplicity

### **Porter's Sweet Spot:**
- **SaaS applications** with fixed role structures
- **Enterprise applications** with well-defined hierarchies  
- **Microservices** with service-specific roles
- **High-performance** applications where DB queries are a bottleneck

**Note:** For true multi-tenancy (shared codebase, tenant-specific roles), consider database-heavy packages. Porter's class-based approach is optimized for applications where access control reflects business logic, not tenant-variable data.

--- 

## Roadmap & Community Input

As a **new package**, your feedback directly shapes Porter's future! I am actively seeking community input and suggestions to prioritize features and ensure Porter evolves into the most valuable tool for your Laravel app.

### 🎯 **Potential Features (Vote & Discuss!)**

#### 🔒 **Assignment Constraints & Actions**
Advanced assignment rules with contextual validation and conditional actions.

**Benefits:**
- 🎯 Conditional role assignments based on business rules
- ⏰ Time-based assignment expiration
- 📊 Assignment quotas and limits
- 🔄 Automatic assignment workflows
- 🧮 Assignment validation with custom constraints

#### 🌐 **REST API Endpoints**
Ready-to-use API endpoints for role management.

**Benefits:**
- 📱 Mobile app integration
- 🔗 Third-party service connectivity
- ⚡ Frontend SPA support
- 📊 External dashboard integration


### 🗳️ **Help Me Decide!**

I want to build what YOU need most. Please share your feedback on:

1. **Which feature would have the biggest impact on your projects?**
2. **What specific use cases do you have in mind?**
3. **Are there other features not listed that would be valuable?**
4. **What's the best way for the community to provide ongoing feedback?**

#### 💬 **Community Feedback Options:**

We welcome your feedback! Please use:

- **GitHub Discussions** for ongoing feature conversations.
- **Project Wiki** for collaborative roadmap planning.
- **GitHub Issues** for bug reports and feature requests.

#### 🎖️ **Recognition**

Contributors who provide valuable feedback will be:
- 📜 **Credited** in release notes
- 🏷️ **Mentioned** as community advisors
- 🚀 **Early access** to beta features
- 💬 **Direct input** on API design decisions

--- 

## Core Features

- 🎯 **Assignment-Focused Design**: Treats roles as business assignments with contextual logic
- 🏗️ **Individual Role Classes**: Each role is its own focused class extending `BaseRole`
- 🚀 **Ultra-Minimal Architecture**: Just 3 core components for assignment management
- 🔥 **Blazing Performance**: Optimized for speed with minimal database interaction and built-in caching
- 🔒 **Enhanced Security**: Assignment keys encrypted with Laravel's built-in encryption
- 🎨 **Perfect Laravel Integration**: Custom Blade directives, middleware, plus seamless Gates and Policies

**🔗 [Complete Core Features Guide →](docs/core-features.md)**

Learn about individual role classes, ultra-minimal architecture, blazing performance optimizations, latest features, and perfect Laravel integration.

--- 

## Real-World Use Cases

Porter's flexible Assignable-Roleable-Roster pattern adapts to diverse business scenarios:

### Team Collaboration Platform
```php
// Teams (Assignable) can have roles on Projects (Roleable)
Porter::assign($developmentTeam, $mobileApp, 'lead_developer');
Porter::assign($designTeam, $mobileApp, 'ui_designer');
Porter::assign($qaTeam, $mobileApp, 'tester');

// Users can also have individual roles
Porter::assign($projectManager, $mobileApp, 'project_owner');
```

### SaaS Feature Consumption
```php
// Organizations (Assignable) get feature access on Subscriptions (Roleable)
Porter::assign($organization, $premiumSubscription, 'analytics_access');
Porter::assign($organization, $premiumSubscription, 'api_access');
Porter::assign($organization, $enterpriseSubscription, 'white_label');

// Check feature access
if ($organization->hasRoleOn($subscription, 'analytics_access')) {
    // Show analytics dashboard
}
```

### Document Management System
```php
// Users/Departments (Assignable) have roles on Documents/Folders (Roleable)
Porter::assign($user, $confidentialDocument, 'viewer');
Porter::assign($legalDepartment, $contractsFolder, 'editor');
Porter::assign($hrTeam, $personnelFolder, 'admin');

// Granular document permissions
if ($user->hasRoleOn($document, 'editor')) {
    // Allow document editing
}
```

### Project Management System
```php
// Multiple assignment types for complex project structures
Porter::assign($developer, $project, 'contributor');
Porter::assign($clientCompany, $project, 'stakeholder');
Porter::assign($vendorTeam, $project, 'external_consultant');

// Role hierarchy checks
$userRole = Porter::getRoleOn($user, $project);
if ($userRole && $userRole->isHigherThan(new Contributor())) {
    // Allow project configuration
}
```

### Multi-Tenant Application
```php
// Users (Assignable) have roles on Tenants/Workspaces (Roleable)
Porter::assign($user, $workspace, 'admin');
Porter::assign($user, $anotherWorkspace, 'member');

// Cross-tenant role isolation
$workspaceRoles = Porter::getRolesOn($user, $workspace);
// Only returns roles for this specific workspace
```

### Enterprise Hierarchy Management
```php
// Departments (Assignable) have roles on Divisions/Subsidiaries (Roleable)
Porter::assign($financeTeam, $subsidiary, 'budget_approver');
Porter::assign($auditDepartment, $division, 'compliance_reviewer');
Porter::assign($executiveTeam, $corporation, 'strategic_decision_maker');

// Business rule validation in role classes
final class BudgetApprover extends BaseRole
{
    public function getMaxApprovalAmount(): int {
        return 500000; // $500k limit
    }
    
    public function canApproveInRegion(string $region): bool {
        return in_array($region, $this->getAllowedRegions());
    }
}
```

---

## Suggested Usage

### Quick Start

```php
use Hdaklue\Porter\Facades\Porter;

// Basic role operations
Porter::assign($user, $project, 'admin');
$isAdmin = $user->hasRoleOn($project, 'admin');
Porter::changeRoleOn($user, $project, 'editor');
```

### Create Role Classes

```bash
# Interactive role creation with guided setup
php artisan porter:create

# Or use the dynamic role factory
$admin = RoleFactory::admin();
```

**🔗 [Complete Usage Guide →](docs/suggested-usage.md)**

Learn about role creation methods, real-world examples (SaaS, E-commerce, Healthcare), advanced patterns, testing strategies, and configuration best practices.

--- 

## Installation

```bash
composer require hdaklue/porter
```

**Flexible installation** with automatic setup:

```bash
# Basic installation - creates Porter directory with BaseRole only
php artisan porter:install

# Full installation - includes 6 default role classes with proper hierarchy
php artisan porter:install --roles
```

The install command:
✅ Publishes configuration file
✅ Publishes and runs migrations
✅ Creates Porter directory with configurable namespace
✅ Optionally creates 6 default role classes (Admin, Manager, Editor, Contributor, Viewer, Guest)
✅ Provides contextual next-step guidance
✅ Blocks installation in production environment for safety

--- 

## Advanced Features

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

### Enhanced Roster Model with Scopes

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

### Custom Role Classes with Business Logic

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

--- 

## Configuration

The `config/porter.php` file contains all package settings with configurable options:

```php
return [
    // ID Strategy - Works with your existing models
    'id_strategy' => env('PORTER_ID_STRATEGY', 'ulid'),

    // Database connection
    'database_connection' => env('PORTER_DB_CONNECTION'),

    // Role Directory & Namespace Configuration
    'directory' => env('PORTER_DIRECTORY', app_path('Porter')),
    'namespace' => env('PORTER_NAMESPACE', 'App\\Porter'),

    // Your role classes (auto-populated by porter:install --roles)
    'roles' => [
        App\Porter\Admin::class,
        App\Porter\Manager::class,
        App\Porter\Editor::class,
        // ... add your custom roles here
    ],

    // Security settings
    'security' => [
        'assignment_strategy' => env('PORTER_ASSIGNMENT_STRATEGY', 'replace'), // 'replace' or 'add'
        'key_storage' => env('PORTER_KEY_STORAGE', 'hashed'),  // 'hashed' or 'plain'
        'auto_generate_keys' => env('PORTER_AUTO_KEYS', true),
    ],

    // Caching
    'cache' => [
        'enabled' => env('PORTER_CACHE_ENABLED', true),
        'ttl' => env('PORTER_CACHE_TTL', 3600), // 1 hour
    ],
];
```

### Security Configuration

```php
// .env file
PORTER_ASSIGNMENT_STRATEGY=replace  # Default: Replaces existing roles
PORTER_ASSIGNMENT_STRATEGY=add      # Adds new roles alongside existing ones

PORTER_KEY_STORAGE=hashed     # Secure (default) - SHA256 hashed role keys
PORTER_KEY_STORAGE=plain      # Debug mode - Plain text role keys

PORTER_AUTO_KEYS=true         # Auto-generate keys from class names
PORTER_AUTO_KEYS=false        # Manual key definition required
```

--- 

## Laravel Integration

Porter integrates seamlessly with Laravel's authorization system - Gates, Policies, Blade directives, and middleware all work naturally with Porter's entity-specific roles.

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

// Custom Blade Directives
@hasAssignmentOn($user, $project, new Admin())
    <button>Admin Actions</button>
@endhasAssignmentOn

@isAssignedTo($user, $project) 
    <div>User has a role on this project</div>
@endisAssignedTo

// Route Middleware for Role Protection
Route::middleware('porter.role:admin')->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
});

// Entity-specific role middleware  
Route::middleware('porter.role_on:admin,Project,{project}')->group(function () {
    Route::get('/projects/{project}/admin', [ProjectController::class, 'admin']);
});
```

### Custom Blade Directives

Porter provides Blade directives that correspond directly to the trait methods:

```blade
{{-- Check if user has specific assignment --}}
@hasAssignmentOn($user, $project, new Admin())
    <div class="admin-panel">
        <h3>Admin Controls</h3>
        <button>Manage Project</button>
    </div>
@endhasAssignmentOn

{{-- Check if user has any assignment on entity --}}
@isAssignedTo($user, $organization)
    <div class="member-badge">
        Organization Member
    </div>
@endisAssignedTo
```

### Route Middleware

Porter includes two middleware for protecting routes:

```php
// Protect routes requiring specific roles (any entity)
Route::middleware('porter.role:admin')->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/admin/users', [AdminController::class, 'users']);
});

// Protect routes requiring roles on specific entities
Route::middleware('porter.role_on:admin,Project,{project}')->group(function () {
    Route::get('/projects/{project}/settings', [ProjectController::class, 'settings']);
    Route::post('/projects/{project}/delete', [ProjectController::class, 'destroy']);
});

// Middleware parameters:
// porter.role_on:{role},{EntityClass},{routeParameter}
Route::middleware('porter.role_on:manager,Organization,{organization}')->group(function () {
    Route::resource('organizations.teams', TeamController::class);
});

// "Any Role" functionality - user must have ANY role on the entity
Route::middleware('porter.role_on:project,*')->group(function () {
    Route::get('/projects/{project}/dashboard', [ProjectController::class, 'dashboard']);
    Route::get('/projects/{project}/activity', [ProjectController::class, 'activity']);
});

// Alternative syntax using 'anyrole' keyword
Route::middleware('porter.role:anyrole')->group(function () {
    Route::get('/projects/{project}/members', [ProjectController::class, 'members']);
});
```

**🔗 [Complete Laravel Integration Guide →](docs/laravel-integration.md)**

Learn about Policies, Middleware, Blade directives, Form Requests, API Resources, Event Listeners, and Testing with Porter.

--- 

## Migration Strategy

> *"Porter adapts to YOUR existing models - no changes required!"*

### Zero-Downtime Migration Strategy

Porter works **parallel** to your existing role system:

```php
// Phase 1: Install Porter (zero risk)
composer require hdaklue/porter
php artisan porter:install
php artisan migrate  // Just adds the `roaster` table

// Phase 2: Add traits to existing models (optional)
class User extends Authenticatable
{
    use HasUlids;  // Add this trait for modern ID strategy

    // All existing code works unchanged!
}

// Phase 3: Gradually migrate role checks
// Old system keeps working:
if ($user->hasRole('admin')) { /* existing code */ }

// New Porter system runs parallel:
if ($user->hasRoleOn($project, 'admin')) { /* Porter */ }

// Phase 4: Switch over when ready (no rush!)
```

### Flexible ID Strategy

```php
// config/porter.php
'id_strategy' => 'integer',  // For auto-increment IDs (default Laravel)
// OR
'id_strategy' => 'ulid',     // For modern ULID IDs
// OR
'id_strategy' => 'uuid',     // For UUID IDs
```

--- 

## Performance

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

**Performance Benefits:**
- 🚀 **Fewer database queries** - single table operations
- 🏃 **Fast role assignments** - simple database operations  
- 💾 **Minimal codebase** - focused architecture with 8 core classes
- 🧠 **Efficient memory usage** - individual role classes
- ⚡ **No foreign key overhead** - polymorphic relationships

--- 

## CLI Commands

Porter provides several Artisan commands for role management:

### Installation Commands
```bash
# Basic installation (config, migrations, Porter directory)
php artisan porter:install

# Full installation with default roles
php artisan porter:install --roles

# Force overwrite existing files
php artisan porter:install --roles --force
```

### Role Management Commands  
```bash
# Interactive role creation with guided setup
php artisan porter:create

# Create specific role with description
php artisan porter:create ProjectManager --description="Manages development projects"

# List all available roles
php artisan porter:list

# Validate Porter setup and configuration  
php artisan porter:doctor
```

### Command Features
- **Interactive Mode**: Guided role creation with automatic level calculation
- **Smart Level Management**: Automatic hierarchy management (lowest, highest, lower, higher)
- **Config-Driven**: Uses directory and namespace from configuration
- **Production Safe**: Install command blocks execution in production
- **Force Override**: `--force` flag for overwriting existing files
- **Type Safety**: All generated roles implement `RoleContract`

--- 

## Testing

Porter features comprehensive testing with **74 tests** and **321 assertions** covering real-world scenarios and edge cases, with continuous integration across multiple PHP and Laravel versions.

```bash
# Run complete test suite
vendor/bin/pest

# Run all tests including feature tests
vendor/bin/pest tests/

# Run with coverage reporting (requires xdebug)
vendor/bin/pest --coverage

# Test specific components
vendor/bin/pest tests/Feature/RoleValidatorTest.php    # Performance & caching
vendor/bin/pest tests/Feature/RoleManagerDatabaseTest.php  # Database operations
vendor/bin/pest tests/Feature/CreateRoleCommandTest.php    # Interactive commands
```

### Test Coverage
- **RoleValidator** (23 tests) - Caching, validation, and hierarchy calculations
- **Commands** (14 tests) - Interactive role creation and installation  
- **Database** (19 tests) - Role assignments and model relationships
- **Unit Tests** (12 tests) - Core role logic and factory methods
- **Integration** (6 tests) - Laravel compatibility and feature integration

### Continuous Integration
- **GitHub Actions** - Automated testing across PHP 8.2-8.3 and Laravel 11-12
- **Compatibility Matrix** - Tests all supported version combinations  
- **Performance Validation** - Ensures speed benchmarks are maintained
- **Security Testing** - Validates encryption and role key protection
- **Database Migration Testing** - Tests across multiple database engines


--- 

## Requirements

- **PHP 8.2+** - Modern language features (required by Laravel 11+)
- **Laravel 11.0+ | 12.0+** - Framework compatibility
- **Database with JSON support** - MySQL 5.7+, PostgreSQL 9.5+, SQLite 3.1+

--- 

## Contributing

I welcome contributions! Please see our [Contributing Guide](CONTRIBUTING.md) for details.

**Ways to help:**
- 🐛 Report bugs and edge cases
- 📝 Improve documentation
- 🧪 Write additional tests
- 💡 Suggest new features
- 🌟 Share your use cases
- 🗳️ Vote on roadmap features

--- 

## License

MIT License. Free for commercial and personal use.

--- 

**Porter** - Keep it simple, keep it fast, keep it focused. 🚪

*Built with ❤️ for developers who appreciate clean architecture and domain-driven design.*
