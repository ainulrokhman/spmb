<?php

namespace App\Services;

use App\Contracts\UserServiceInterface;
use App\Models\User;
use App\Support\SystemAccess;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserService implements UserServiceInterface
{
    /**
     * Store a newly created user.
     */
    public function createUser(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        } else {
            $user->assignRole(SystemAccess::USER_ROLE); // default role
        }

        return $user;
    }

    /**
     * Update the specified user.
     */
    public function updateUser(User $user, array $data): User
    {
        // Prevent modifying the Super Admin seed email to avoid breaking the quick-login demo accounts
        if ($user->email === SystemAccess::SUPER_ADMIN_EMAIL && ($data['email'] ?? '') !== SystemAccess::SUPER_ADMIN_EMAIL) {
            throw new \InvalidArgumentException('Cannot change the email of the core Super Admin account.');
        }

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (!empty($data['password'])) {
            $userData['password'] = Hash::make($data['password']);
        }

        $user->update($userData);

        $roles = $data['roles'] ?? [];
        // Keep SuperAdmin role on the seed SuperAdmin account to prevent locking out
        if ($user->email === SystemAccess::SUPER_ADMIN_EMAIL) {
            if (!in_array(SystemAccess::SUPER_ADMIN_ROLE, $roles)) {
                $roles[] = SystemAccess::SUPER_ADMIN_ROLE;
            }
        }

        $user->syncRoles($roles);

        return $user;
    }

    /**
     * Delete the specified user.
     */
    public function deleteUser(User $user): bool
    {
        // Don't let users delete themselves
        if (Auth::id() === $user->id) {
            throw new \LogicException('You cannot delete your own account.');
        }

        // Don't delete seed SuperAdmin account
        if ($user->email === SystemAccess::SUPER_ADMIN_EMAIL) {
            throw new \LogicException('Cannot delete the system Super Admin account.');
        }

        return (bool) $user->delete();
    }
}
