<?php

declare(strict_types=1);

use Hdaklue\Porter\Middleware\RequireRoleOn;
use Hdaklue\Porter\RoleManager;
use Hdaklue\Porter\Tests\Fixtures\TestProject;
use Hdaklue\Porter\Tests\Fixtures\TestUser;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test tables
    Schema::create('test_users', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamps();
    });

    Schema::create('test_projects', function ($table) {
        $table->id();
        $table->string('name');
        $table->text('description')->nullable();
        $table->timestamps();
    });

    $this->roleManager = app(RoleManager::class);
    $this->middleware = new RequireRoleOn($this->roleManager);

    $this->user = TestUser::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->project = TestProject::create(['name' => 'Test Project']);
});

test('middleware allows access when user has required role on specified entity', function () {
    $this->roleManager->assign($this->user, $this->project, 'TestAdmin');

    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    // Mock route with project parameter
    $route = new Route(['GET'], '/test', []);
    $route->bind($request);
    $route->setParameter('project', $this->project);
    $request->setRouteResolver(fn () => $route);

    $next = fn ($req) => response('success');

    // Test Laravel's parameter parsing: porter.role_on:project,TestAdmin
    $response = $this->middleware->handle($request, $next, 'project', 'TestAdmin');

    expect($response->getContent())->toBe('success');
});

test('middleware blocks access when user lacks required role on specified entity', function () {
    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    // Mock route with project parameter
    $route = new Route(['GET'], '/test', []);
    $route->bind($request);
    $route->setParameter('project', $this->project);
    $request->setRouteResolver(fn () => $route);

    $next = fn ($req) => response('success');

    // Test Laravel's parameter parsing: porter.role_on:project,TestAdmin
    // The middleware will throw HttpException via abort() for non-JSON requests
    expect(fn () => $this->middleware->handle($request, $next, 'project', 'TestAdmin'))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('middleware handles multiple roles on specified entity', function () {
    $this->roleManager->assign($this->user, $this->project, 'TestEditor');

    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    // Mock route with project parameter
    $route = new Route(['GET'], '/test', []);
    $route->bind($request);
    $route->setParameter('project', $this->project);
    $request->setRouteResolver(fn () => $route);

    $next = fn ($req) => response('success');

    // Test Laravel's parameter parsing: porter.role_on:project,TestAdmin,TestEditor
    $response = $this->middleware->handle($request, $next, 'project', 'TestAdmin', 'TestEditor');

    expect($response->getContent())->toBe('success');
});

test('middleware handles any role wildcard on specified entity', function () {
    $this->roleManager->assign($this->user, $this->project, 'TestEditor');

    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    // Mock route with project parameter
    $route = new Route(['GET'], '/test', []);
    $route->bind($request);
    $route->setParameter('project', $this->project);
    $request->setRouteResolver(fn () => $route);

    $next = fn ($req) => response('success');

    // Test Laravel's parameter parsing: porter.role_on:project,*
    $response = $this->middleware->handle($request, $next, 'project', '*');

    expect($response->getContent())->toBe('success');
});

test('middleware handles anyrole parameter on specified entity', function () {
    $this->roleManager->assign($this->user, $this->project, 'TestAdmin');

    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    // Mock route with project parameter
    $route = new Route(['GET'], '/test', []);
    $route->bind($request);
    $route->setParameter('project', $this->project);
    $request->setRouteResolver(fn () => $route);

    $next = fn ($req) => response('success');

    // Test Laravel's parameter parsing: porter.role_on:project,anyrole
    $response = $this->middleware->handle($request, $next, 'project', 'anyrole');

    expect($response->getContent())->toBe('success');
});

test('middleware throws exception when no roles specified', function () {
    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    $next = fn ($req) => response('success');

    expect(fn () => $this->middleware->handle($request, $next, 'project'))
        ->toThrow(\InvalidArgumentException::class, 'At least one role must be specified');
});

test('middleware throws exception when entity parameter empty', function () {
    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    $next = fn ($req) => response('success');

    expect(fn () => $this->middleware->handle($request, $next, '', 'TestAdmin'))
        ->toThrow(\InvalidArgumentException::class, 'Entity parameter name must be specified');
});

test('middleware throws exception when user not authenticated', function () {
    $request = Request::create('/test');
    $request->setUserResolver(fn () => null);

    $next = fn ($req) => response('success');

    expect(fn () => $this->middleware->handle($request, $next, 'project', 'TestAdmin'))
        ->toThrow(AuthenticationException::class, 'User must be authenticated');
});

test('middleware throws exception when specified route parameter is not RoleableEntity', function () {
    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    // Mock route with non-RoleableEntity parameter
    $route = new Route(['GET'], '/test', []);
    $route->bind($request);
    $route->setParameter('project', 'not-an-entity'); // String instead of entity
    $request->setRouteResolver(fn () => $route);

    $next = fn ($req) => response('success');

    expect(fn () => $this->middleware->handle($request, $next, 'project', 'TestAdmin'))
        ->toThrow(\InvalidArgumentException::class, "Route parameter 'project' must be a RoleableEntity instance");
});

test('middleware throws exception when specified route parameter does not exist', function () {
    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    // Mock route without the specified parameter
    $route = new Route(['GET'], '/test', []);
    $route->bind($request);
    $request->setRouteResolver(fn () => $route);

    $next = fn ($req) => response('success');

    expect(fn () => $this->middleware->handle($request, $next, 'nonexistent', 'TestAdmin'))
        ->toThrow(\InvalidArgumentException::class, "Route parameter 'nonexistent' must be a RoleableEntity instance");
});

test('middleware handles whitespace in role parameters', function () {
    $this->roleManager->assign($this->user, $this->project, 'TestAdmin');

    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    $route = new Route(['GET'], '/test', []);
    $route->bind($request);
    $route->setParameter('project', $this->project);
    $request->setRouteResolver(fn () => $route);

    $next = fn ($req) => response('success');

    // Test with whitespace - Laravel would pass this as separate parameters
    $response = $this->middleware->handle($request, $next, 'project', ' TestAdmin ', '  TestEditor  ');

    expect($response->getContent())->toBe('success');
});

test('middleware returns detailed JSON error for API requests', function () {
    $request = Request::create('/api/test', 'GET');
    $request->headers->set('Accept', 'application/json');
    $request->setUserResolver(fn () => $this->user);

    $route = new Route(['GET'], '/api/test', []);
    $route->bind($request);
    $route->setParameter('project', $this->project);
    $request->setRouteResolver(fn () => $route);

    $next = fn ($req) => response('success');

    $response = $this->middleware->handle($request, $next, 'project', 'TestAdmin');

    expect($response->getStatusCode())->toBe(Response::HTTP_FORBIDDEN);
    expect($response->headers->get('content-type'))->toContain('application/json');

    $data = json_decode($response->getContent(), true);
    expect($data['message'])->toContain('Access denied');
    expect($data['required_roles'])->toBe(['TestAdmin']);
    expect($data['entity_type'])->toBe(TestProject::class);
    expect($data['entity_id'])->toBe($this->project->getKey());
    expect($data['entity_parameter'])->toBe('project');
});

test('middleware ignores non-existent roles gracefully', function () {
    $this->roleManager->assign($this->user, $this->project, 'TestAdmin');

    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    $route = new Route(['GET'], '/test', []);
    $route->bind($request);
    $route->setParameter('project', $this->project);
    $request->setRouteResolver(fn () => $route);

    $next = fn ($req) => response('success');

    // Mix of valid and invalid roles - should pass if user has valid role
    $response = $this->middleware->handle($request, $next, 'project', 'NonExistentRole', 'TestAdmin', 'AnotherFakeRole');

    expect($response->getContent())->toBe('success');
});

test('middleware throws exception when no valid roles provided', function () {
    $request = Request::create('/test');
    $request->setUserResolver(fn () => $this->user);

    $route = new Route(['GET'], '/test', []);
    $route->bind($request);
    $route->setParameter('project', $this->project);
    $request->setRouteResolver(fn () => $route);

    $next = fn ($req) => response('success');

    // Only invalid roles
    expect(fn () => $this->middleware->handle($request, $next, 'project', 'NonExistentRole', 'AnotherFakeRole'))
        ->toThrow(\InvalidArgumentException::class, 'No valid roles specified');
});
