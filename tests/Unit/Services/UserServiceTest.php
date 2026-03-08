<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;
use App\Models\User;
use App\Services\UserService;
use App\Repositories\UserRepository;
use App\Repositories\SekolahRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;

class UserServiceTest extends TestCase
{
    protected UserService $service;
    protected MockInterface $repository;
    protected MockInterface $sekolahRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = Mockery::mock(UserRepository::class);
        $this->sekolahRepository = Mockery::mock(SekolahRepository::class);
        $this->service = new UserService($this->repository, $this->sekolahRepository);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ── getAllPaginated ───────────────────────────────────────────────

    public function test_get_all_paginated_delegates_to_repository(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 20);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(20)
            ->andReturn($paginator);

        $result = $this->service->getAllPaginated();

        $this->assertSame($paginator, $result);
    }

    public function test_get_all_paginated_with_custom_per_page(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 10);

        $this->repository
            ->shouldReceive('getAll')
            ->once()
            ->with(10)
            ->andReturn($paginator);

        $result = $this->service->getAllPaginated(10);

        $this->assertSame($paginator, $result);
    }

    // ── getActiveSekolahs ────────────────────────────────────────────

    public function test_get_active_sekolahs_delegates_to_sekolah_repository(): void
    {
        $collection = new Collection();

        $this->sekolahRepository
            ->shouldReceive('getFiltered')
            ->once()
            ->with(true)
            ->andReturn($collection);

        $result = $this->service->getActiveSekolahs();

        $this->assertSame($collection, $result);
    }

    // ── getById ──────────────────────────────────────────────────────

    public function test_get_by_id_returns_user(): void
    {
        $user = Mockery::mock(User::class);

        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with('user-1')
            ->andReturn($user);

        $result = $this->service->getById('user-1');

        $this->assertSame($user, $result);
    }

    public function test_get_by_id_returns_null_when_not_found(): void
    {
        $this->repository
            ->shouldReceive('findById')
            ->once()
            ->with('nonexistent')
            ->andReturn(null);

        $result = $this->service->getById('nonexistent');

        $this->assertNull($result);
    }

    // ── createUser ───────────────────────────────────────────────────

    public function test_create_user_hashes_password(): void
    {
        $user = Mockery::mock(User::class);

        Hash::shouldReceive('make')
            ->once()
            ->with('plain-password')
            ->andReturn('hashed-password');

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                return $data['name'] === 'John Doe'
                    && $data['email'] === 'john@example.com'
                    && $data['password'] === 'hashed-password';
            }))
            ->andReturn($user);

        $result = $this->service->createUser([
            'name'     => 'John Doe',
            'email'    => 'john@example.com',
            'password' => 'plain-password',
        ]);

        $this->assertSame($user, $result);
    }

    public function test_create_user_without_password(): void
    {
        $user = Mockery::mock(User::class);

        $this->repository
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($data) {
                // Password should not be in the data since it wasn't provided
                return $data['name'] === 'John Doe'
                    && !isset($data['password']);
            }))
            ->andReturn($user);

        $result = $this->service->createUser([
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertSame($user, $result);
    }

    // ── updateUser ───────────────────────────────────────────────────

    public function test_update_user_hashes_password_when_provided(): void
    {
        $user = Mockery::mock(User::class);

        Hash::shouldReceive('make')
            ->once()
            ->with('new-password')
            ->andReturn('hashed-new-password');

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($user, Mockery::on(function ($data) {
                return $data['name'] === 'Jane Doe'
                    && $data['password'] === 'hashed-new-password';
            }))
            ->andReturn(true);

        $result = $this->service->updateUser($user, [
            'name'     => 'Jane Doe',
            'password' => 'new-password',
        ]);

        $this->assertSame($user, $result);
    }

    public function test_update_user_removes_empty_password(): void
    {
        $user = Mockery::mock(User::class);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($user, Mockery::on(function ($data) {
                // Empty password should be removed, not hashed
                return $data['name'] === 'Jane Doe'
                    && !array_key_exists('password', $data);
            }))
            ->andReturn(true);

        $result = $this->service->updateUser($user, [
            'name'     => 'Jane Doe',
            'password' => '',
        ]);

        $this->assertSame($user, $result);
    }

    public function test_update_user_removes_null_password(): void
    {
        $user = Mockery::mock(User::class);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($user, Mockery::on(function ($data) {
                // Null password should be removed, not hashed
                return !array_key_exists('password', $data);
            }))
            ->andReturn(true);

        $result = $this->service->updateUser($user, [
            'name'     => 'Jane Doe',
            'password' => null,
        ]);

        $this->assertSame($user, $result);
    }

    public function test_update_user_without_password_key(): void
    {
        $user = Mockery::mock(User::class);

        $this->repository
            ->shouldReceive('update')
            ->once()
            ->with($user, ['name' => 'Jane Doe'])
            ->andReturn(true);

        $result = $this->service->updateUser($user, [
            'name' => 'Jane Doe',
        ]);

        $this->assertSame($user, $result);
    }

    // ── deleteUser ───────────────────────────────────────────────────

    public function test_delete_user_delegates_to_repository(): void
    {
        $user = Mockery::mock(User::class);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($user)
            ->andReturn(true);

        $result = $this->service->deleteUser($user);

        $this->assertTrue($result);
    }

    public function test_delete_user_returns_false_on_failure(): void
    {
        $user = Mockery::mock(User::class);

        $this->repository
            ->shouldReceive('delete')
            ->once()
            ->with($user)
            ->andReturn(false);

        $result = $this->service->deleteUser($user);

        $this->assertFalse($result);
    }
}
