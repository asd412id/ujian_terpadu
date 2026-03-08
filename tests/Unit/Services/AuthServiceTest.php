<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Services\AuthService;
use App\Repositories\AuthRepository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthServiceTest extends TestCase
{
    protected AuthService $service;
    protected MockInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(AuthRepository::class);
        $this->service = new AuthService($this->repository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── loginUser — success ────────────────────────────────────

    public function test_login_user_success_with_role(): void
    {
        $credentials = [
            'email' => 'admin@test.com',
            'password' => 'secret',
            'role' => 'admin',
            'remember' => true,
        ];

        $user = Mockery::mock();
        $user->shouldReceive('update')
            ->once()
            ->with(Mockery::on(fn ($data) => isset($data['last_login_at'])))
            ->andReturn(true);
        $user->shouldReceive('getDashboardRoute')
            ->once()
            ->andReturn('dinas.dashboard');

        $guard = Mockery::mock();
        $guard->shouldReceive('attempt')
            ->once()
            ->with([
                'email' => 'admin@test.com',
                'password' => 'secret',
                'is_active' => true,
                'role' => 'admin',
            ], true)
            ->andReturn(true);
        $guard->shouldReceive('user')
            ->once()
            ->andReturn($user);

        Auth::shouldReceive('guard')
            ->with('web')
            ->andReturn($guard);

        $result = $this->service->loginUser($credentials);

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('dashboard_route', $result);
        $this->assertEquals($user, $result['user']);
        $this->assertEquals('dinas.dashboard', $result['dashboard_route']);
    }

    public function test_login_user_success_without_role(): void
    {
        $credentials = [
            'email' => 'user@test.com',
            'password' => 'secret',
        ];

        $user = Mockery::mock();
        $user->shouldReceive('update')->once()->andReturn(true);
        $user->shouldReceive('getDashboardRoute')->once()->andReturn('sekolah.dashboard');

        $guard = Mockery::mock();
        $guard->shouldReceive('attempt')
            ->once()
            ->with([
                'email' => 'user@test.com',
                'password' => 'secret',
                'is_active' => true,
            ], false)
            ->andReturn(true);
        $guard->shouldReceive('user')
            ->once()
            ->andReturn($user);

        Auth::shouldReceive('guard')
            ->with('web')
            ->andReturn($guard);

        $result = $this->service->loginUser($credentials);

        $this->assertEquals('sekolah.dashboard', $result['dashboard_route']);
    }

    public function test_login_user_success_with_remember_false(): void
    {
        $credentials = [
            'email' => 'user@test.com',
            'password' => 'pw',
            'remember' => false,
        ];

        $user = Mockery::mock();
        $user->shouldReceive('update')->once()->andReturn(true);
        $user->shouldReceive('getDashboardRoute')->once()->andReturn('dashboard');

        $guard = Mockery::mock();
        $guard->shouldReceive('attempt')
            ->once()
            ->with(Mockery::type('array'), false)
            ->andReturn(true);
        $guard->shouldReceive('user')->once()->andReturn($user);

        Auth::shouldReceive('guard')->with('web')->andReturn($guard);

        $result = $this->service->loginUser($credentials);
        $this->assertIsArray($result);
    }

    // ── loginUser — failure ────────────────────────────────────

    public function test_login_user_throws_on_invalid_credentials(): void
    {
        $credentials = [
            'email' => 'bad@test.com',
            'password' => 'wrong',
        ];

        $guard = Mockery::mock();
        $guard->shouldReceive('attempt')
            ->once()
            ->andReturn(false);

        Auth::shouldReceive('guard')
            ->with('web')
            ->andReturn($guard);

        $this->expectException(ValidationException::class);
        $this->service->loginUser($credentials);
    }

    public function test_login_user_error_message_references_email(): void
    {
        $credentials = ['email' => 'x@x.com', 'password' => 'y'];

        $guard = Mockery::mock();
        $guard->shouldReceive('attempt')->once()->andReturn(false);

        Auth::shouldReceive('guard')->with('web')->andReturn($guard);

        try {
            $this->service->loginUser($credentials);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('email', $e->errors());
            $this->assertStringContainsString('salah', $e->errors()['email'][0]);
        }
    }

    public function test_login_user_with_empty_credentials(): void
    {
        $credentials = [];

        $guard = Mockery::mock();
        $guard->shouldReceive('attempt')
            ->once()
            ->with([
                'email' => '',
                'password' => '',
                'is_active' => true,
            ], false)
            ->andReturn(false);

        Auth::shouldReceive('guard')->with('web')->andReturn($guard);

        $this->expectException(ValidationException::class);
        $this->service->loginUser($credentials);
    }

    // ── loginPeserta ───────────────────────────────────────────
    // Uses Peserta model directly (Eloquent query).

    public function test_login_peserta_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'loginPeserta');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('credentials', $params[0]->getName());
        $this->assertEquals('array', $reflection->getReturnType()->getName());
    }

    // ── logout ─────────────────────────────────────────────────

    public function test_logout_web_guard(): void
    {
        $guard = Mockery::mock();
        $guard->shouldReceive('logout')->once();

        Auth::shouldReceive('guard')
            ->with('web')
            ->once()
            ->andReturn($guard);

        $this->service->logout('web');
        $this->addToAssertionCount(1); // Mockery expectations count as assertions
    }

    public function test_logout_peserta_guard(): void
    {
        $guard = Mockery::mock();
        $guard->shouldReceive('logout')->once();

        Auth::shouldReceive('guard')
            ->with('peserta')
            ->once()
            ->andReturn($guard);

        $this->service->logout('peserta');
        $this->addToAssertionCount(1);
    }

    public function test_logout_defaults_to_web_guard(): void
    {
        $guard = Mockery::mock();
        $guard->shouldReceive('logout')->once();

        Auth::shouldReceive('guard')
            ->with('web')
            ->once()
            ->andReturn($guard);

        $this->service->logout();
        $this->addToAssertionCount(1);
    }

    public function test_logout_return_type_is_void(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'logout');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('void', $returnType->getName());
    }

    // ── validatePesertaToken ───────────────────────────────────
    // Uses Peserta model directly.

    public function test_validate_peserta_token_signature(): void
    {
        $reflection = new \ReflectionMethod($this->service, 'validatePesertaToken');
        $params = $reflection->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('token', $params[0]->getName());
        $this->assertEquals('string', $params[0]->getType()->getName());
    }
}
