# KluePortal Auth/Tenant/RBAC Package

A Laravel package providing multi-tenant RBAC authentication with shared database and Redis sessions.

## Features

- **Multi-tenant RBAC** with sophisticated role assignment
- **Shared core database** across all services  
- **Shared Redis sessions** for seamless authentication
- **Performance optimized** with 1-hour caching (226ms response, 5MB memory)
- **Filament integration** with panel-based tenant switching
- **ULID support** with proper morph class mapping
- **Type-safe DTOs** with automatic validation using ValidatedDTO

## Installation

```bash
composer require klueportal/auth-tenant-rbac
```

## Quick Start

The package will automatically register its service provider. Publish the configuration:

```bash
php artisan vendor:publish --tag=auth-tenant-rbac-config
```

## Configuration

The package uses your application's default database connection and Redis session store to ensure shared authentication state across services.

## Usage

### Basic Authentication
```php
// The package extends Laravel's standard authentication
// Your existing auth code will work unchanged
```

### Tenant Management
```php
// Switch active tenant
$user->switchActiveTenant($tenant);

// Check tenant access
$user->canAccessTenant($tenant);

// Get assigned tenants
$tenants = $user->getAssignedTenants();
```

### Role Management
```php
// Assign role to user on entity
RoleManager::assign($user, $entity, 'admin');

// Check role
$hasRole = $user->hasRoleOn($entity, 'admin');

// Get participants with roles
$participants = RoleManager::getParticipants($entity);
```

### Data Transfer Objects (DTOs)

The package includes type-safe DTOs for consistent data handling:

#### Tenant Creation
```php
use MargRbac\DTOs\Tenant\CreateTenantDto;
use MargRbac\DTOs\Tenant\TenantMemberDto;

// Create tenant with members
$dto = CreateTenantDto::fromArray([
    'name' => 'My Organization',
    'members' => [
        ['name' => 'john@example.com', 'role' => 'admin'],
        ['name' => 'jane@example.com', 'role' => 'viewer'],
    ]
]);

CreateTenant::run($dto, $user);
```

#### Invitation Management
```php
use MargRbac\DTOs\Invitation\InvitationDTO;
use MargRbac\DTOs\Invitation\TenantInvitationRoleDto;

// Create invitation
$invitationDto = InvitationDTO::fromArray([
    'sender' => $user,
    'email' => 'newuser@example.com',
    'name' => 'New User'
]);

// Define tenant roles for invitation
$tenantRoles = [
    TenantInvitationRoleDto::fromArray([
        'tenant_id' => $tenant->id,
        'role_id' => $adminRole->id
    ])
];

InviteTenantMember::run($invitationDto, $tenantRoles);
```

#### Available DTOs
- `CreateTenantDto` - Tenant creation with members
- `TenantMemberDto` - Individual tenant member with role
- `InvitationDTO` - User invitation details
- `TenantInvitationRoleDto` - Tenant-specific role assignment for invitations

## Architecture

### Package Structure
```
src/
├── Actions/           # Lorisleiva Actions for business logic
├── Collections/       # Specialized Eloquent collections
├── Concerns/          # Reusable traits
├── Contracts/         # Interfaces and contracts
├── DTOs/             # Validated Data Transfer Objects
│   ├── Invitation/   # Invitation-related DTOs
│   └── Tenant/       # Tenant management DTOs
├── Enums/            # Role hierarchy enums
├── Events/           # Laravel events
├── Models/           # Eloquent models
└── Services/         # Service layer classes
```

### Key Architectural Decisions
- **Contract-based design** - Heavy use of interfaces for dependency injection
- **Service layer pattern** - Business logic encapsulated in services
- **Event-driven architecture** - Domain events for decoupling
- **Validated DTOs** - Type-safe data handling with automatic validation
- **Shared database strategy** - Core authentication shared across microservices

## Requirements

- Laravel 12+
- Filament 4+
- PHP 8.3+
- Redis (for sessions and caching)
- MySQL/PostgreSQL
- wendelladriel/laravel-validated-dto (for DTOs)

## License

Proprietary - KluePortal Team