# LaraRbac - Laravel RBAC with Constraint-Based Validation

A lightweight, high-performance Laravel RBAC package with JSON-based constraint validation system. Built for modern applications that need flexible, context-aware permission checking.

## Features

- **Hybrid Architecture** - Database roles + JSON constraint storage for fast validation
- **Individual Role Classes** - Each role extends `BaseRole` for clean organization
- **Constraint-Based Validation** - Context-aware permission checking with type-safe rules
- **JSON Storage** - Lightning-fast constraint validation with zero DB queries
- **Extensible Rule System** - Easy to add custom validation rules
- **Type-Safe** - Full PHP 8.3+ type hints and enums
- **Minimal API** - Under 300 lines per class, focused and clean

## Installation

```bash
composer require hdaklue/lara-rbac
```

## Quick Start

Publish configuration and migrations:

```bash
php artisan vendor:publish --tag=lararbac-config
php artisan vendor:publish --tag=lararbac-migrations
php artisan vendor:publish --tag=lararbac-constraints
php artisan migrate
```

## Usage

### Role Management

```php
use Hdaklue\LaraRbac\Services\Role\RoleAssignmentService;

$roleService = app(RoleAssignmentService::class);

// Assign role to user on entity
$roleService->assign($user, $project, 'admin');

// Check if user has role
$hasRole = $roleService->hasRoleOn($user, $project, 'admin');

// Get all participants with roles
$participants = $roleService->getParticipants($project);
```

### Constraint-Based Permissions

```php
use Hdaklue\LaraRbac\Objects\ConstraintSet;
use Hdaklue\LaraRbac\Context\Constraint;
use Hdaklue\LaraRbac\Context\Rules\{LessThanOrEqual, Equals, In};

// Create constraint set
$budgetConstraint = ConstraintSet::make('Budget Approval', 'Budget approval constraints')
    ->constrain(Constraint::make('amount', new LessThanOrEqual(), 50000))
    ->constrain(Constraint::make('department', new Equals(), 'finance'))
    ->constrain(Constraint::make('priority', new In(), ['normal', 'high', 'critical']))
    ->save();

// Use with permission service
use Hdaklue\LaraRbac\Services\Permission\PermissionManagementService;

$permissionService = app(PermissionManagementService::class);

// Check permission with context
$canApprove = $permissionService->can($user, 'budget_approval', [
    'amount' => 25000,
    'department' => 'finance',
    'priority' => 'high'
]);
```

### Role Classes

Create individual role classes extending `BaseRole`:

```php
use Hdaklue\LaraRbac\Roles\BaseRole;

class ProjectManager extends BaseRole
{
    protected string $name = 'Project Manager';
    protected string $description = 'Can manage projects and team members';
    
    public function getPermissions(): array
    {
        return [
            'project_edit',
            'member_management',
            'budget_approval'
        ];
    }
}
```

### Available Validation Rules

The package includes built-in validation rules:

- **Equals** - Exact equality (`===`)
- **NotEquals** - Inequality (`!==`)
- **LessThan** / **GreaterThan** - Numeric comparisons
- **LessThanOrEqual** - Numeric less than or equal
- **Between** - Range validation `[min, max]`
- **Contains** - String/array contains
- **In** - Array membership
- **IsNull** / **IsNotNull** - Null checks

### Context Validation Examples

```php
// Budget approval with multiple constraints
$constraints = [
    'amount' => 75000,           // Must be <= 50000 (fails)
    'department' => 'finance',   // Must equal 'finance' (passes)
    'priority' => 'high'         // Must be in array (passes)
];

$result = $constraintSet->allows(null, $constraints); // false (amount too high)

// Document access with security clearance
$documentConstraint = ConstraintSet::make('Document Access')
    ->constrain(Constraint::make('security_level', new In(), ['confidential', 'secret']))
    ->constrain(Constraint::make('access_time', new Between(), [9, 17]));

$canAccess = $documentConstraint->allows(null, [
    'security_level' => 'confidential',
    'access_time' => 14  // 2 PM
]); // true
```

## Architecture

### Package Structure

```
src/
├── Collections/Role/        # Specialized collections for role data
├── Concerns/Role/          # Role-related traits and behaviors
├── Context/                # Constraint validation system
│   ├── Rules/             # Individual validation rule classes
│   ├── Constraint.php     # Single constraint definition
│   ├── ContextHelper.php  # Context value utilities
│   └── ConstraintValidator.php
├── Contracts/             # Interfaces for dependency injection
├── Events/Role/          # Role assignment events
├── Facades/              # Laravel facades
├── Models/               # Eloquent models (Role, RoleableHasRole)
├── Objects/              # Core objects (ConstraintSet)
├── Providers/            # Service providers
├── Roles/                # Individual role class implementations
└── Services/             # Business logic services
    ├── Permission/       # Permission management
    └── Role/            # Role assignment logic
```

### Key Design Patterns

- **Individual Rule Classes** - Each validation rule is its own focused class
- **Constraint-Based Validation** - Context-aware permission checking
- **JSON Storage** - Fast constraint loading without database hits
- **Service Layer Pattern** - Clean separation of business logic
- **Contract-Based Architecture** - Heavy use of interfaces
- **Event-Driven** - Role changes dispatch domain events

### Performance Characteristics

- **Role Assignment**: Cached for 1 hour via Redis
- **Constraint Validation**: JSON-based, significantly faster than database queries
- **Zero Database Queries**: For constraint validation after initial load
- **Optimized Memory Usage**: Lightweight objects with minimal overhead

## Configuration

### Database

The package uses a `constraints` JSON column in the `roles` table to store permission keys, bridging the database role system with JSON constraint validation.

```php
// Role model with constraints
$role = Role::find($id);
$role->constraints = ['budget_approval', 'document_access'];
$role->save();
```

### Constraint Storage

Constraints are stored as JSON files in `config/constraints/`:

```json
{
    "name": "Budget Approval",
    "description": "Budget approval with amount limits",
    "context_rules": [
        {
            "field": "amount",
            "operator": "<=",
            "value": 50000
        },
        {
            "field": "department", 
            "operator": "===",
            "value": "finance"
        }
    ]
}
```

## Extending the System

### Custom Validation Rules

Create new rules by extending `BaseRule`:

```php
use Hdaklue\LaraRbac\Context\Rules\BaseRule;

class StartsWith extends BaseRule
{
    public function validate(mixed $contextValue, mixed $ruleValue): bool
    {
        return is_string($contextValue) && str_starts_with($contextValue, $ruleValue);
    }
    
    public function isValidRuleValue(mixed $value): bool
    {
        return is_string($value);
    }
}
```

### Custom Role Classes

```php
class CustomRole extends BaseRole
{
    protected string $name = 'Custom Role';
    
    public function getPermissions(): array
    {
        return ['custom_permission'];
    }
    
    public function canPerform(string $action, array $context = []): bool
    {
        // Custom role-specific logic
        return parent::canPerform($action, $context);
    }
}
```

## Requirements

- **Laravel 12+** - Modern framework features
- **PHP 8.3+** - Type hints, enums, readonly properties
- **Redis** - Caching and session storage
- **MySQL/PostgreSQL** - Database storage with JSON column support

## Testing

```bash
# Run package tests
vendor/bin/phpunit

# Run with coverage
vendor/bin/phpunit --coverage-text
```

## License

MIT License - Free for commercial and personal use.