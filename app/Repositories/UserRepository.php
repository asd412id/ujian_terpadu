<?php

namespace App\Repositories;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class UserRepository
{
    public function __construct(
        protected User $model
    ) {}

    /**
     * Get all users with sekolah relation, paginated.
     */
    public function getAll(int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with('sekolah')
            ->orderBy('role')
            ->paginate($perPage);
    }

    /**
     * Get filtered users.
     */
    public function getFiltered(?string $role = null, ?string $search = null, ?bool $isActive = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->model
            ->with('sekolah')
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($search, fn ($q) => $q->where(function ($sub) use ($search) {
                $sub->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->when($isActive !== null, fn ($q) => $q->where('is_active', $isActive))
            ->orderBy('role')
            ->paginate($perPage);
    }

    /**
     * Find user by ID.
     */
    public function findById(string $id): ?User
    {
        return $this->model->find($id);
    }

    /**
     * Find user by email.
     */
    public function findByEmail(string $email): ?User
    {
        return $this->model->where('email', $email)->first();
    }

    /**
     * Create a new user.
     */
    public function create(array $data): User
    {
        return $this->model->create($data);
    }

    /**
     * Update an existing user.
     */
    public function update(User $user, array $data): bool
    {
        return $user->update($data);
    }

    /**
     * Hapus user secara permanen dari database.
     */
    public function delete(User $user): bool
    {
        return $user->delete();
    }
}
