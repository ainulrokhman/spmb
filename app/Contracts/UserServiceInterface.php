<?php

namespace App\Contracts;

use App\Models\User;

interface UserServiceInterface
{
    /**
     * Store a newly created user.
     *
     * @param array $data
     * @return User
     */
    public function createUser(array $data): User;

    /**
     * Update the specified user.
     *
     * @param User $user
     * @param array $data
     * @return User
     */
    public function updateUser(User $user, array $data): User;

    /**
     * Delete the specified user.
     *
     * @param User $user
     * @return bool
     * @throws \Exception
     */
    public function deleteUser(User $user): bool;
}
