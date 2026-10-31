<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ProfileServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Models\User;

class ProfileController extends Controller
{
    protected ProfileServiceInterface $profileService;

    /**
     * ProfileController constructor.
     */
    public function __construct(ProfileServiceInterface $profileService)
    {
        $this->profileService = $profileService;
    }

    /**
     * Show the profile edit form.
     */
    public function edit()
    {
        $user = auth()->user();

        return view('admin.profile.edit', compact('user'));
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(UpdateProfileRequest $request)
    {
        /** @var User $user */
        $user = auth()->user();

        $this->profileService->updateProfile($user, $request->validated());

        return redirect()->route('admin.profile.edit')
            ->with('success', 'Profile updated successfully.');
    }
}
