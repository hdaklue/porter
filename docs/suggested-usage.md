# Suggested Usage

This guide provides comprehensive examples of how to integrate and use Porter in your Laravel application, from basic role assignments to complex real-world scenarios.

## Quick Start

### Basic Role Assignment

```php
use Hdaklue\Porter\Facades\Porter;

// Assign role
Porter::assign($user, $project, 'admin');

// Check role
$isAdmin = $user->hasRoleOn($project, 'admin');

// Remove role
Porter::remove($user, $project);

// Change role
Porter::changeRoleOn($user, $project, 'editor');
```

### Model Integration

Add the concerns to your models:

```php
use Hdaklue\Porter\Concerns\{CanBeAssignedToEntity, ReceivesRoleAssignments};

class User extends Authenticatable
{
    use CanBeAssignedToEntity;
    
    // Your existing User model code...
}

class Project extends Model  
{
    use ReceivesRoleAssignments;
    
    // Your existing Project model code...
}
```

## Creating Role Classes

Porter provides multiple ways to create role classes:

### Method 1: Interactive Role Creation (Recommended)

```bash
# Interactive command with guided setup
php artisan porter:create

# The command will ask:
# - Role name (auto-converted to PascalCase)
# - Description 
# - Creation mode (lowest, highest, lower, higher)
# - Target role (for lower/higher modes)

# Example interaction:
# What is the role name? project-manager
# What is the role description? Manages development projects
# Select creation mode: higher
# Which role do you want to reference? Editor
# ✅ Role 'ProjectManager' created successfully!
```

### Method 2: Command Line Arguments

```bash
# Create specific role with description
php artisan porter:create ProjectManager --description="Manages development projects"

# Will prompt for creation mode and level calculation
```

### Method 3: Dynamic Role Factory

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

### Method 4: Manual Role Classes

```php
use App\Porter\BaseRole; // Your application's base role

final class ProjectManager extends BaseRole
{
    public function getName(): string
    {
        return 'project_manager';
    }

    public function getLevel(): int
    {
        return 7;
    }
    
    public function getDescription(): string
    {
        return 'Manages development projects and team coordination';
    }
}
```

## Real-World Usage Examples

### Multi-Tenant SaaS Application

Perfect for workspace-based applications like Slack, Notion, or Trello:

```php
// Organization-level roles
Porter::assign($user, $organization, 'admin');
Porter::assign($manager, $organization, 'manager');

// Project-level roles within organization
Porter::assign($developer, $project, 'contributor');
Porter::assign($lead, $project, 'project_lead');

// Check hierarchical access
if ($user->hasRoleOn($organization, 'admin')) {
    // Admin has access to all projects in organization
    // Implement business logic here
}

// Policy example
public function update(User $user, Project $project)
{
    // Check if user is project lead OR organization admin
    return $user->hasRoleOn($project, 'project_lead') 
        || $user->hasRoleOn($project->organization, 'admin');
}
```

### E-commerce Platform

Ideal for marketplace applications with multiple stores:

```php
// Store management
Porter::assign($storeOwner, $store, 'owner');
Porter::assign($manager, $store, 'manager');
Porter::assign($cashier, $store, 'cashier');

// Product catalog management
Porter::assign($catalogManager, $catalog, 'catalog_manager');

// Order fulfillment
Porter::assign($fulfillmentTeam, $warehouse, 'fulfillment_staff');

// Business logic example
public function canProcessRefund(User $user, Order $order)
{
    $store = $order->store;
    
    // Only store managers and owners can process refunds
    return $user->hasRoleOn($store, 'manager') 
        || $user->hasRoleOn($store, 'owner');
}
```

### Healthcare System

Perfect for department-based medical applications:

```php
// Department roles
Porter::assign($doctor, $department, 'attending_physician');
Porter::assign($nurse, $department, 'head_nurse');
Porter::assign($resident, $department, 'resident');

// Patient care assignments
Porter::assign($doctor, $patient, 'primary_care_physician');
Porter::assign($specialist, $patient, 'consulting_specialist');

// Medical record access control
public function canViewMedicalRecord(User $user, Patient $patient)
{
    // Primary physician always has access
    if ($user->hasRoleOn($patient, 'primary_care_physician')) {
        return true;
    }
    
    // Department staff can view patients in their department
    if ($user->hasRoleOn($patient->department, 'attending_physician') ||
        $user->hasRoleOn($patient->department, 'head_nurse')) {
        return true;
    }
    
    return false;
}
```

### Content Management System

Great for editorial workflows:

```php
// Publication roles
Porter::assign($editor, $publication, 'editor_in_chief');
Porter::assign($writer, $publication, 'staff_writer');
Porter::assign($freelancer, $publication, 'contributor');

// Article-specific assignments
Porter::assign($author, $article, 'author');
Porter::assign($editor, $article, 'assigned_editor');

// Publishing workflow
public function canPublishArticle(User $user, Article $article)
{
    $publication = $article->publication;
    
    // Editor in chief can publish anything
    if ($user->hasRoleOn($publication, 'editor_in_chief')) {
        return true;
    }
    
    // Assigned editors can publish their assigned articles
    if ($user->hasRoleOn($article, 'assigned_editor')) {
        return true;
    }
    
    return false;
}
```

### Educational System

Perfect for schools with multiple hierarchies:

```php
// School administration
Porter::assign($principal, $school, 'principal');
Porter::assign($viceP, $school, 'vice_principal');

// Department roles
Porter::assign($deptHead, $department, 'department_head');
Porter::assign($teacher, $department, 'teacher');

// Class-specific assignments
Porter::assign($teacher, $class, 'class_teacher');
Porter::assign($assistant, $class, 'teaching_assistant');

// Grade access control
public function canEnterGrades(User $user, Class $class)
{
    // Class teacher can always enter grades
    if ($user->hasRoleOn($class, 'class_teacher')) {
        return true;
    }
    
    // Department heads can enter grades for their department's classes
    if ($user->hasRoleOn($class->department, 'department_head')) {
        return true;
    }
    
    return false;
}
```

## Advanced Usage Patterns

### Role Hierarchy and Comparisons

```php
use App\Porter\{Admin, Manager, Editor, Contributor};

// Create role instances
$admin = new Admin();           // Level 10
$manager = new Manager();       // Level 7  
$editor = new Editor();         // Level 5
$contributor = new Contributor(); // Level 3

// Smart role comparisons
$admin->isHigherThan($manager);     // true
$manager->isHigherThan($editor);    // true
$editor->isLowerThan($admin);       // true
$admin->equals(new Admin());        // true

// Business logic using hierarchy
public function canManageUser(User $currentUser, User $targetUser, Project $project): bool
{
    $currentRole = Porter::getRoleOn($currentUser, $project);
    $targetRole = Porter::getRoleOn($targetUser, $project);
    
    // Can only manage users with lower roles
    return $currentRole && $targetRole && $currentRole->isHigherThan($targetRole);
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
    
    public function getDirectReports(): int
    {
        return 50; // Maximum 50 direct reports
    }
}

// Usage in controllers
public function approveBudget(Request $request, Budget $budget)
{
    $user = $request->user();
    
    if (!$user->hasRoleOn($budget->company, 'regional_manager')) {
        abort(403, 'Insufficient permissions');
    }
    
    $role = Porter::getRoleOn($user, $budget->company);
    
    // Check region access
    if (!$role->canAccessRegion($budget->region)) {
        abort(403, 'Cannot access this region');
    }
    
    // Check budget limit
    if ($budget->amount > $role->getMaxBudgetApproval()) {
        abort(403, 'Budget exceeds approval limit');
    }
    
    $budget->approve();
}
```

### Enhanced Roster Model Usage

```php
use Hdaklue\Porter\Models\Roster;

// Query role assignments with scopes
$userAssignments = Roster::forAssignable(User::class, $user->id)->get();
$projectRoles = Roster::forRoleable(Project::class, $project->id)->get();
$adminAssignments = Roster::withRoleName('admin')->get();

// Chain scopes for complex queries
$recentAdminAssignments = Roster::withRoleName('admin')
    ->where('created_at', '>=', now()->subDays(30))
    ->with(['assignable', 'roleable'])
    ->get();

// Audit trail functionality
foreach ($assignments as $assignment) {
    echo "Assigned: {$assignment->description} on {$assignment->created_at}";
    // Output: "User #123 has role 'admin' on Project #456 on 2024-01-15 14:30:00"
}

// Role assignment analytics
$roleDistribution = Roster::forRoleable(Project::class, $project->id)
    ->selectRaw('role_key, count(*) as count')
    ->groupBy('role_key')
    ->get();
```

### Middleware Integration

```php
// Create custom middleware
class RequireRoleOnEntity
{
    public function handle($request, Closure $next, $role, $entityParam = null)
    {
        $user = $request->user();
        $entityParam = $entityParam ?? 'project'; // Default parameter name
        $entity = $request->route($entityParam);
        
        if (!$user || !$entity || !$user->hasRoleOn($entity, $role)) {
            abort(403, "Required role: {$role}");
        }
        
        return $next($request);
    }
}

// Register in Kernel.php
protected $routeMiddleware = [
    'role.on' => RequireRoleOnEntity::class,
];

// Use in routes
Route::middleware('role.on:admin,project')->group(function () {
    Route::put('/projects/{project}', [ProjectController::class, 'update']);
    Route::delete('/projects/{project}', [ProjectController::class, 'destroy']);
});
```

### Event-Driven Architecture

```php
use Hdaklue\Porter\Events\{RoleAssigned, RoleChanged, RoleRemoved};

// Listen to role events
class NotifyRoleChange
{
    public function handle(RoleAssigned $event)
    {
        $user = $event->assignable;
        $entity = $event->roleable;
        $role = $event->role;
        
        // Send notification
        $user->notify(new RoleAssignedNotification($entity, $role));
        
        // Log for audit
        Log::info("Role assigned", [
            'user_id' => $user->id,
            'entity_type' => get_class($entity),
            'entity_id' => $entity->id,
            'role' => $role,
        ]);
    }
}
```

## Testing with Porter

### Unit Testing Role Classes

```php
use App\Porter\Admin;
use PHPUnit\Framework\TestCase;

class AdminRoleTest extends TestCase
{
    public function test_admin_role_properties()
    {
        $admin = new Admin();
        
        $this->assertEquals('admin', $admin->getName());
        $this->assertEquals(10, $admin->getLevel());
        $this->assertStringContains('administrator', $admin->getDescription());
    }
    
    public function test_role_hierarchy()
    {
        $admin = new Admin();
        $manager = new Manager();
        
        $this->assertTrue($admin->isHigherThan($manager));
        $this->assertFalse($manager->isHigherThan($admin));
    }
}
```

### Feature Testing Role Assignments

```php
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAssignmentTest extends TestCase
{
    use RefreshDatabase;
    
    public function test_user_can_be_assigned_role()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        
        Porter::assign($user, $project, 'admin');
        
        $this->assertTrue($user->hasRoleOn($project, 'admin'));
        $this->assertFalse($user->hasRoleOn($project, 'editor'));
    }
    
    public function test_role_change_updates_assignment()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        
        Porter::assign($user, $project, 'admin');
        Porter::changeRoleOn($user, $project, 'editor');
        
        $this->assertFalse($user->hasRoleOn($project, 'admin'));
        $this->assertTrue($user->hasRoleOn($project, 'editor'));
    }
}
```

### Testing Policies

```php
class ProjectPolicyTest extends TestCase
{
    public function test_admin_can_update_project()
    {
        $admin = User::factory()->create();
        $project = Project::factory()->create();
        
        Porter::assign($admin, $project, 'admin');
        
        $this->assertTrue($admin->can('update', $project));
    }
    
    public function test_contributor_cannot_delete_project()
    {
        $contributor = User::factory()->create();
        $project = Project::factory()->create();
        
        Porter::assign($contributor, $project, 'contributor');
        
        $this->assertFalse($contributor->can('delete', $project));
    }
}
```

## Configuration Best Practices

### Environment-Specific Settings

```php
// .env
PORTER_ASSIGNMENT_STRATEGY=replace    # Production: replace existing roles
PORTER_KEY_STORAGE=hashed            # Production: hashed keys for security
PORTER_CACHE_ENABLED=true            # Production: enable caching
PORTER_CACHE_TTL=3600                # Production: 1 hour cache

# Development settings
PORTER_ASSIGNMENT_STRATEGY=add       # Dev: allow multiple roles for testing
PORTER_KEY_STORAGE=plain            # Dev: plain keys for debugging
```

### Security Configuration

```php
// config/porter.php
'security' => [
    'assignment_strategy' => env('PORTER_ASSIGNMENT_STRATEGY', 'replace'),
    'key_storage' => env('PORTER_KEY_STORAGE', 'hashed'),
    'auto_generate_keys' => env('PORTER_AUTO_KEYS', true),
],
```

This comprehensive guide should help you implement Porter in any Laravel application, from simple role assignments to complex hierarchical systems.