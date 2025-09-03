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
 * Middleware to require specific roles on a specific entity parameter.
 *
 * Usage in routes:
 * Route::get('/projects/{project}/edit', [ProjectController::class, 'edit'])
 *     ->middleware('porter.role_on:project,admin,editor');
 * Route::delete('/organizations/{org}/members/{user}', [MemberController::class, 'destroy'])
 *     ->middleware('porter.role_on:org,admin,manager');
 */
final class RequireRoleOn
{
    public function __construct(
        private readonly RoleManagerContract $roleManager,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next, string $entityParam, string ...$roles): SymfonyResponse
    {
        if (empty($roles)) {
            throw new \InvalidArgumentException('At least one role must be specified for role middleware.');
        }

        if (empty(trim($entityParam))) {
            throw new \InvalidArgumentException('Entity parameter name must be specified.');
        }

        $user = $request->user();
        if (! $user instanceof AssignableEntity) {
            throw new AuthenticationException('User must be authenticated and implement AssignableEntity.');
        }

        // Get target entity from specified route parameter
        $target = $request->route($entityParam);
        if (! $target instanceof RoleableEntity) {
            throw new \InvalidArgumentException(
                "Route parameter '{$entityParam}' must be a RoleableEntity instance."
            );
        }

        // Check if user has any of the required roles on the target entity
        $hasRequiredRole = false;
        $validRoles = [];

        foreach ($roles as $roleName) {
            $roleName = trim($roleName);
            try {
                $role = BaseRole::make($roleName);
                $validRoles[] = $roleName;

                if ($this->roleManager->hasRoleOn($user, $target, $role)) {
                    $hasRequiredRole = true;
                    break;
                }
            } catch (\InvalidArgumentException) {
                // Role doesn't exist, skip it
                continue;
            }
        }

        if (empty($validRoles)) {
            throw new \InvalidArgumentException(
                'No valid roles specified. Available roles: '.
                implode(', ', array_map(fn ($r) => $r->getName(), BaseRole::all()))
            );
        }

        if (! $hasRequiredRole) {
            return $this->unauthorized($request, $validRoles, $entityParam, $target);
        }

        return $next($request);
    }

    /**
     * Handle unauthorized access.
     */
    private function unauthorized(
        Request $request,
        array $roles,
        string $entityParam,
        RoleableEntity $target
    ): SymfonyResponse {
        $message = sprintf(
            'Access denied. Required role(s) on %s: %s',
            class_basename($target),
            implode(', ', $roles)
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'required_roles' => $roles,
                'entity_type' => get_class($target),
                'entity_id' => $target->getKey(),
                'entity_parameter' => $entityParam,
            ], Response::HTTP_FORBIDDEN);
        }

        if ($request->wantsJson()) {
            return response()->json(['error' => $message], Response::HTTP_FORBIDDEN);
        }

        // For web requests
        abort(Response::HTTP_FORBIDDEN, $message);
    }
}
