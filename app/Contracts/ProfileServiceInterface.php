<?php

namespace App\Contracts;

use App\Models\User;

interface ProfileServiceInterface
{
    /**
     * Update the authenticated user's own profile.
     *
     * @param  array  $data  Validated data; may contain an UploadedFile under the "avatar" key.
     */
    public function updateProfile(User $user, array $data): User;

    /**
     * Determine whether the user is the protected Super Admin seed account.
     *
     * The seed account's email must never change, otherwise the documented
     * demo login would silently break.
     */
    public function isProtectedSeedAccount(User $user): bool;
}
