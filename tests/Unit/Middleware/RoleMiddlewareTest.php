<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\RoleMiddleware;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class RoleMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private RoleMiddleware $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new RoleMiddleware();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $request = Request::create('/dinas/dashboard', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('OK'), 'super_admin');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('login', $response->headers->get('Location'));
    }

    public function test_inactive_user_is_logged_out(): void
    {
        $user = User::factory()->create(['is_active' => false, 'role' => 'super_admin']);
        Auth::login($user);

        $request = Request::create('/dinas/dashboard', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('OK'), 'super_admin');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertFalse(Auth::check());
    }

    public function test_user_with_correct_role_passes(): void
    {
        $user = User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
        Auth::login($user);

        $request = Request::create('/dinas/dashboard', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('OK'), 'super_admin');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getContent());
    }

    public function test_user_with_wrong_role_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'pengawas', 'is_active' => true]);
        Auth::login($user);

        $request = Request::create('/dinas/dashboard', 'GET');

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->middleware->handle($request, fn () => new Response('OK'), 'super_admin');
    }

    public function test_user_with_one_of_multiple_roles_passes(): void
    {
        $user = User::factory()->create(['role' => 'admin_dinas', 'is_active' => true]);
        Auth::login($user);

        $request = Request::create('/dinas/dashboard', 'GET');
        $response = $this->middleware->handle(
            $request,
            fn () => new Response('OK'),
            'super_admin', 'admin_dinas'
        );

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_no_roles_specified_any_active_user_passes(): void
    {
        $user = User::factory()->create(['role' => 'pengawas', 'is_active' => true]);
        Auth::login($user);

        $request = Request::create('/some-page', 'GET');
        $response = $this->middleware->handle($request, fn () => new Response('OK'));

        $this->assertEquals(200, $response->getStatusCode());
    }
}
