<?php

declare(strict_types=1);

namespace Hdaklue\Porter\Middleware;

use Closure;
use Hdaklue\Porter\Contracts\AssignableEntity;
use Hdaklue\Porter\Contracts\RoleableEntity;
use Hdaklue\Porter\Contracts\RoleManagerContract;
use Hdaklue\Porter\Roles\BaseRole;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Middleware to require specific roles for route access.
 *
 * Usage in routes:
 * Route::get('/admin', AdminController::class)->middleware('porter.role:admin,manager');
 * Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])
 *     ->middleware('porter.role:admin,editor');
 *
 * Any role functionality (requires any role on detected entity):
 * Route::get('/projects/{project}/dashboard', [ProjectController::class, 'dashboard'])
 *     ->middleware('porter.role:*');
 * Route::get('/projects/{project}/activity', [ProjectController::class, 'activity'])
 *     ->middleware('porter.role:anyrole');
 */
final readonly class RequireRole
{
    public function __construct(
        private RoleManagerContract $roleManager,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, string ...$roles): SymfonyResponse
    {
        if (empty($roles)) {
            throw new \InvalidArgumentException('At least one role must be specified for role middleware.');
        }

        $user = $request->user();
        if (! $user instanceof AssignableEntity) {
            throw new AuthenticationException('User must be authenticated and implement AssignableEntity.');
        }

        // Try to get the target entity from route parameters
        $target = $this->extractTargetFromRoute($request);

        if (! $target) {
            // If no target found, skip role check (global permission check)
            // You might want to implement global role checking here
            return $next($request);
        }

        // Check for "any role" functionality
        if (count($roles) === 1 && (trim($roles[0]) === '*' || trim($roles[0]) === 'anyrole')) {
            $hasRequiredRole = $this->roleManager->hasAnyRoleOn($user, $target);
        } else {
            // Check if a user has any of the required roles on the target entity
            $hasRequiredRole = false;
            foreach ($roles as $roleName) {
                try {
                    $role = BaseRole::make(trim($roleName));
                    if ($this->roleManager->hasRoleOn($user, $target, $role)) {
                        $hasRequiredRole = true;
                        break;
                    }
                } catch (\InvalidArgumentException) {
                    // Role doesn't exist, continue checking other roles
                    continue;
                }
            }
        }

        if (! $hasRequiredRole) {
            return $this->unauthorized($request, $roles);
        }

        return $next($request);
    }

    /**
     * Extract target entity from route parameters.
     */
    private function extractTargetFromRoute(Request $request): ?RoleableEntity
    {
        // Common parameter names for roleable entities
        $possibleParameters = [
            'project', 'organization', 'team', 'workspace',
            'entity', 'resource', 'target',
        ];

        foreach ($possibleParameters as $paramName) {
            $entity = $request->route($paramName);
            if ($entity instanceof RoleableEntity) {
                return $entity;
            }
        }

        // Try to find any RoleableEntity in route parameters
        foreach ($request->route()->parameters() as $parameter) {
            if ($parameter instanceof RoleableEntity) {
                return $parameter;
            }
        }

        return null;
    }

    /**
     * Handle unauthorized access.
     */
    private function unauthorized(Request $request, array $roles): SymfonyResponse
    {
        $message = sprintf(
            'Access denied. Required role(s): %s',
            implode(', ', $roles)
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'required_roles' => $roles,
            ], Response::HTTP_FORBIDDEN);
        }

        if ($request->wantsJson()) {
            return response()->json(['error' => $message], Response::HTTP_FORBIDDEN);
        }

        // For web requests, you might want to redirect or show a view
        abort(Response::HTTP_FORBIDDEN, $message);
    }
}
