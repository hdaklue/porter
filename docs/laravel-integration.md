# Laravel Integration

Porter integrates seamlessly with Laravel's existing authorization system, working alongside Gates, Policies, and Blade directives.

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
        return $user->hasRoleOn($project, 'admin')
            || $user->hasRoleOn($project, 'manager');
    }

    public function delete(User $user, Project $project)
    {
        return $user->hasRoleOn($project, 'admin');
    }

    public function invite(User $user, Project $project)
    {
        $role = Porter::getRoleOn($user, $project);
        return $role && $role->getLevel() >= 5; // Manager level or higher
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

### Custom Blade Directives

You can also create custom directives for Porter-specific checks:

```php
// In AppServiceProvider.php
use Illuminate\Support\Facades\Blade;
use Hdaklue\Porter\Facades\Porter;

public function boot()
{
    Blade::if('hasRoleOn', function ($entity, $role) {
        return auth()->check() && Porter::hasRoleOn(auth()->user(), $entity, $role);
    });
}
```

Usage in Blade:

```blade
@hasRoleOn($project, 'admin')
    <div class="admin-controls">
        <!-- Admin-only content -->
    </div>
@endhasRoleOn
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
        
        return $this->user()->hasRoleOn($project, 'admin') 
            || $this->user()->hasRoleOn($project, 'manager');
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
                'can_edit' => auth()->user()->hasRoleOn($this->resource, 'admin') 
                    || auth()->user()->hasRoleOn($this->resource, 'manager'),
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