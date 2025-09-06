# Laravel Integration

Porter integrates seamlessly with Laravel's existing authorization system, working alongside Gates, Policies, and Blade directives.

**The Game Changer**: Porter's `isAtLeastOn()` method eliminates verbose `hasRole('admin') || hasRole('manager')` patterns with a single hierarchy-aware call. Instead of listing every acceptable role, you simply express business logic: "needs at least manager level." This one-liner approach combines assignment checking + hierarchy comparison, delivering both cleaner code and better maintainability.

## Policy Classes

Porter works perfectly with Laravel's Policy classes for clean, testable authorization logic:

```php
class ProjectPolicy
{
    public function view(User $user, Project $project)
    {
        return $user->hasAnyRoleOn($project);
    }

    public function update(User $user, Project $project)
    {
        return Porter::isAtLeastOn($user, RoleFactory::manager(), $project);
    }

    public function delete(User $user, Project $project)
    {
        return $user->hasRoleOn($project, 'admin');
    }

    public function invite(User $user, Project $project)
    {
        return Porter::isAtLeastOn($user, RoleFactory::manager(), $project);
    }
}
```

### Using Policies in Controllers

```php
class ProjectController extends Controller
{
    public function show(Project $project)
    {
        $this->authorize('view', $project);
        
        return view('projects.show', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $this->authorize('update', $project);
        
        $project->update($request->validated());
        
        return redirect()->route('projects.show', $project);
    }
}
```

## Blade Directives

Use Laravel's `@can` directive with Porter-powered policies:

```blade
@can('view', $project)
    <a href="{{ route('projects.show', $project) }}">View Project</a>
@endcan

@can('update', $project)
    <button class="btn btn-primary">Edit Project</button>
@endcan

@can('delete', $project)
    <form method="POST" action="{{ route('projects.destroy', $project) }}">
        @csrf
        @method('DELETE')
        <button class="btn btn-danger">Delete Project</button>
    </form>
@endcan
```

### Built-in Blade Directives

Porter includes three powerful Blade directives out of the box for seamless template integration:

#### @hasRoleOn - Exact Role Matching
Check if a user has a specific role on a target entity:

```blade
@hasRoleOn($user, $project, 'admin')
    <div class="admin-controls">
        <button class="btn-danger">Delete Project</button>
        <button class="btn-warning">Archive Project</button>
    </div>
@endhasRoleOn

@hasRoleOn($user, $project, 'editor')
    <button class="btn-primary">Edit Content</button>
@endhasRoleOn
```

#### @hasAnyRoleOn - Any Role Detection
Check if a user has any role (useful for participation checks):

```blade
@hasAnyRoleOn($user, $project)
    <div class="project-member-tools">
        <a href="{{ route('projects.dashboard', $project) }}">View Dashboard</a>
        <button onclick="leaveProject()">Leave Project</button>
    </div>
@else
    <button onclick="requestAccess()">Request Access</button>
@endhasAnyRoleOn
```

#### @isAtLeastOn - Hierarchy-Based Authorization
**NEW**: Check if a user has at least the minimum required role level using Porter's role hierarchy:

> **🚨 CRITICAL SECURITY BEHAVIOR**  
> `@isAtLeastOn` returns `false` if the user has **NO role at all** on the target entity, not just insufficient hierarchy. This is "assignment-first, hierarchy-second" behavior.  
> - ✅ User with 'editor' role + checking for 'editor' = `true`  
> - ✅ User with 'admin' role + checking for 'manager' = `true` (hierarchy)  
> - ❌ User with 'viewer' role + checking for 'editor' = `false` (insufficient level)  
> - ❌ User with **no role** + checking for any level = `false` (no assignment)

```blade
@isAtLeastOn($user, RoleFactory::manager(), $project)
    <div class="management-controls">
        <button class="btn-success">Approve Budget</button>
        <button class="btn-info">Assign Tasks</button>
        <button class="btn-secondary">View Reports</button>
    </div>
@endisAtLeastOn

@isAtLeastOn($user, RoleFactory::editor(), $project)
    <div class="content-controls">
        <button class="btn-primary">Edit Content</button>
        <button class="btn-outline">Save Draft</button>
    </div>
@endisAtLeastOn
```

### Why @isAtLeastOn is Game-Changing

Traditional role checking requires you to list every acceptable role:

```blade
{{-- The old way: verbose and error-prone --}}
@if($user->hasRoleOn($project, 'admin') || 
    $user->hasRoleOn($project, 'manager') || 
    $user->hasRoleOn($project, 'team_lead'))
    <button>Manage Team</button>
@endif
```

With `@isAtLeastOn`, you define the minimum requirement once:

```blade
{{-- The Porter way: clean and maintainable --}}
@isAtLeastOn($user, RoleFactory::manager(), $project)
    <button>Manage Team</button>
@endisAtLeastOn
```

**Benefits:**
- **Single Call Performance**: One method call vs multiple OR conditions reduces query overhead
- **Hierarchy-Aware**: Automatically includes higher-level roles (Admin ≥ Manager ≥ Editor)
- **Future-Proof**: Add new high-level roles without updating templates
- **Business Logic**: Expresses intent clearly - "needs at least manager-level access"
- **Type Safety**: Uses RoleFactory for compile-time role validation
- **Maintainable**: Single source of truth for role requirements

### Real-World @isAtLeastOn Examples

#### Progressive Feature Access
```blade
{{-- Basic content access for all members --}}
@hasAnyRoleOn($user, $project)
    <div class="project-overview">
        <h2>{{ $project->name }}</h2>
        <p>{{ $project->description }}</p>
    </div>
@endhasAnyRoleOn

{{-- Content editing for editors and above --}}
@isAtLeastOn($user, RoleFactory::editor(), $project)
    <div class="content-actions">
        <button class="edit-btn">Edit Content</button>
        <button class="preview-btn">Preview Changes</button>
    </div>
@endisAtLeastOn

{{-- Management features for managers and above --}}
@isAtLeastOn($user, RoleFactory::manager(), $project)
    <div class="management-panel">
        <h3>Team Management</h3>
        <button class="invite-btn">Invite Members</button>
        <button class="role-btn">Manage Roles</button>
    </div>
@endisAtLeastOn

{{-- Administrative controls for admins only --}}
@hasRoleOn($user, $project, 'admin')
    <div class="admin-panel">
        <h3>Administration</h3>
        <button class="danger-btn">Delete Project</button>
        <button class="archive-btn">Archive Project</button>
    </div>
@endhasRoleOn
```

#### Dynamic Navigation Menus
```blade
<nav class="project-nav">
    <a href="{{ route('projects.show', $project) }}">Overview</a>
    
    @isAtLeastOn($user, RoleFactory::editor(), $project)
        <a href="{{ route('projects.edit', $project) }}">Edit</a>
        <a href="{{ route('projects.content', $project) }}">Manage Content</a>
    @endisAtLeastOn
    
    @isAtLeastOn($user, RoleFactory::manager(), $project)
        <a href="{{ route('projects.team', $project) }}">Team</a>
        <a href="{{ route('projects.reports', $project) }}">Reports</a>
    @endisAtLeastOn
    
    @hasRoleOn($user, $project, 'admin')
        <a href="{{ route('projects.settings', $project) }}">Settings</a>
    @endhasRoleOn
</nav>
```

#### Budget Approval Workflows
```blade
<div class="budget-request">
    <h3>Budget Request: ${{ number_format($request->amount) }}</h3>
    
    @if($request->amount <= 1000)
        @isAtLeastOn($user, RoleFactory::editor(), $project)
            <button class="approve-btn">Approve Small Budget</button>
        @endisAtLeastOn
    @elseif($request->amount <= 10000)
        @isAtLeastOn($user, RoleFactory::manager(), $project)
            <button class="approve-btn">Approve Medium Budget</button>
        @endisAtLeastOn
    @else
        @hasRoleOn($user, $project, 'admin')
            <button class="approve-btn">Approve Large Budget</button>
        @endhasRoleOn
    @endif
</div>
```

### Advanced Usage Patterns

#### Using with Custom Role Classes
```blade
@isAtLeastOn($user, RoleFactory::teamLead(), $project)
    <div class="team-lead-tools">
        <!-- Custom role with specific business logic -->
    </div>
@endisAtLeastOn
```

#### Combining with Laravel Authorization
```blade
@can('update', $project)
    @isAtLeastOn($user, RoleFactory::editor(), $project)
        <button class="save-btn">Save Changes</button>
    @endisAtLeastOn
@endcan
```

### Custom Blade Directives

You can also create additional custom directives for Porter-specific checks:

```php
// In AppServiceProvider.php
use Illuminate\Support\Facades\Blade;
use Hdaklue\Porter\Facades\Porter;

public function boot()
{
    // Custom directive for current user checks
    Blade::if('canManageProject', function ($project) {
        return auth()->check() && 
               Porter::isAtLeastOn(auth()->user(), RoleFactory::manager(), $project);
    });
}
```

Usage in Blade:

```blade
@canManageProject($project)
    <div class="project-management">
        <!-- Management interface -->
    </div>
@endcanManageProject
```

## Middleware

Create custom middleware for role-based route protection:

```php
class RequireRoleOnEntity
{
    public function handle(Request $request, Closure $next, string $role)
    {
        $entity = $request->route('project'); // or any entity parameter
        
        if (!$request->user()->hasRoleOn($entity, $role)) {
            abort(403, 'Insufficient role permissions');
        }
        
        return $next($request);
    }
}
```

### Register Middleware

```php
// In app/Http/Kernel.php
protected $middlewareAliases = [
    // ... other middleware
    'role.on.entity' => \App\Http\Middleware\RequireRoleOnEntity::class,
];
```

### Use in Routes

```php
// Routes that require specific roles on entities
Route::put('/projects/{project}', [ProjectController::class, 'update'])
    ->middleware('role.on.entity:admin');

Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])
    ->middleware('role.on.entity:admin');

Route::post('/projects/{project}/invite', [ProjectController::class, 'invite'])
    ->middleware('role.on.entity:manager');
```

## Gates

You can also use Laravel Gates with Porter for more complex authorization logic:

```php
// In AuthServiceProvider.php
use Illuminate\Support\Facades\Gate;
use Hdaklue\Porter\Facades\Porter;

public function boot()
{
    Gate::define('manage-project-budget', function (User $user, Project $project, int $amount) {
        if (!Porter::hasRoleOn($user, $project, 'admin')) {
            return false;
        }
        
        $role = Porter::getRoleOn($user, $project);
        return $role && method_exists($role, 'getMaxBudgetApproval') 
            && $amount <= $role->getMaxBudgetApproval();
    });
}
```

## Form Requests

Integrate Porter checks into Form Request validation:

```php
class UpdateProjectRequest extends FormRequest
{
    public function authorize()
    {
        $project = $this->route('project');
        
        return Porter::isAtLeastOn($this->user(), RoleFactory::manager(), $project);
    }

    public function rules()
    {
        $project = $this->route('project');
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ];

        // Only admins can change certain fields
        if ($this->user()->hasRoleOn($project, 'admin')) {
            $rules['budget'] = 'nullable|numeric|min:0';
            $rules['status'] = 'nullable|string|in:active,inactive,archived';
        }

        return $rules;
    }
}
```

## API Resources

Include role information in API responses:

```php
class ProjectResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'user_role' => $this->when(
                auth()->check(),
                Porter::getRoleOn(auth()->user(), $this->resource)
            ),
            'permissions' => $this->when(auth()->check(), [
                'can_edit' => Porter::isAtLeastOn(auth()->user(), RoleFactory::manager(), $this->resource),
                'can_delete' => auth()->user()->hasRoleOn($this->resource, 'admin'),
                'can_invite' => auth()->user()->hasAnyRoleOn($this->resource),
            ]),
        ];
    }
}
```

## Event Listeners

Listen to Porter's role assignment events:

```php
// In EventServiceProvider.php
protected $listen = [
    \Hdaklue\Porter\Events\RoleAssigned::class => [
        \App\Listeners\SendRoleAssignedNotification::class,
        \App\Listeners\LogRoleAssignment::class,
    ],
    \Hdaklue\Porter\Events\RoleChanged::class => [
        \App\Listeners\SendRoleChangedNotification::class,
    ],
    \Hdaklue\Porter\Events\RoleRemoved::class => [
        \App\Listeners\SendRoleRemovedNotification::class,
    ],
];
```

Example event listener:

```php
class SendRoleAssignedNotification
{
    public function handle(RoleAssigned $event)
    {
        $user = $event->user;
        $target = $event->target;
        $role = $event->role;

        // Send notification
        $user->notify(new RoleAssignedNotification($target, $role));
    }
}
```

## Testing

Porter integrates well with Laravel's testing helpers:

```php
class ProjectControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_project()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        
        Porter::assign($user, $project, 'admin');
        
        $this->actingAs($user)
            ->delete(route('projects.destroy', $project))
            ->assertRedirect();
            
        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_manager_cannot_delete_project()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        
        Porter::assign($user, $project, 'manager');
        
        $this->actingAs($user)
            ->delete(route('projects.destroy', $project))
            ->assertForbidden();
    }
}
```

---

## Next Steps

- [Migration Strategy](migration-strategy.md) - Learn how to migrate from existing RBAC systems
- [Performance Optimization](performance.md) - Tips for optimizing Porter in high-traffic applications
- [Advanced Patterns](advanced-patterns.md) - Complex authorization scenarios and solutions