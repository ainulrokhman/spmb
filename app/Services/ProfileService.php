<?php

namespace App\Services;

use App\Contracts\ProfileServiceInterface;
use App\Models\User;
use App\Support\SystemAccess;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class ProfileService implements ProfileServiceInterface
{
    /**
     * The email of the protected Super Admin seed account.
     */
    private const PROTECTED_SEED_EMAIL = SystemAccess::SUPER_ADMIN_EMAIL;

    /**
     * Update the authenticated user's own profile.
     */
    public function updateProfile(User $user, array $data): User
    {
        $user->name = $data['name'];

        // The seed Super Admin email is protected so the documented
        // demo account stays reachable; everyone else may change theirs.
        if (! $this->isProtectedSeedAccount($user) && isset($data['email'])) {
            $user->email = $data['email'];
        }

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $avatar = $data['avatar'] ?? null;
        if ($avatar instanceof UploadedFile) {
            $this->replaceAvatar($user, $avatar);
        }

        $user->save();

        return $user;
    }

    /**
     * Determine whether the user is the protected Super Admin seed account.
     */
    public function isProtectedSeedAccount(User $user): bool
    {
        return $user->email === self::PROTECTED_SEED_EMAIL;
    }

    /**
     * Replace the user's avatar, deleting the previous file if one exists.
     */
    private function replaceAvatar(User $user, UploadedFile $avatar): void
    {
        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->avatar = $avatar->store('avatars', 'public');
    }
}
