<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Repositories\SekolahRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UserService
{
    public function __construct(
        protected UserRepository $repository,
        protected SekolahRepository $sekolahRepository
    ) {}

    /**
     * Get all users with sekolah, paginated (Dinas view).
     */
    public function getAllPaginated(int $perPage = 20): mixed
    {
        return $this->repository->getAll($perPage);
    }

    /**
     * Get active sekolah list (for dropdowns/filters).
     */
    public function getActiveSekolahs(): mixed
    {
        return $this->sekolahRepository->getFiltered(true);
    }

    /**
     * Get a single user by ID.
     */
    public function getById(string $id): ?User
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new user with hashed password.
     */
    public function createUser(array $data): User
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        return $this->repository->create($data);
    }

    /**
     * Update an existing user.
     */
    public function updateUser(User $user, array $data): User
    {
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $this->repository->update($user, $data);
        return $user;
    }

    /**
     * Soft-delete (deactivate) a user.
     */
    public function deleteUser(User $user): bool
    {
        return $this->repository->delete($user);
    }
}
