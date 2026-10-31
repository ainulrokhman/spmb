<?php

namespace App\Http\Requests\Profile;

use App\Contracts\ProfileServiceInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class UpdateProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = $this->user();

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'], // max 2MB
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];

        // The protected seed Super Admin account may not change its email,
        // so the rule is omitted entirely for that account.
        $profileService = app(ProfileServiceInterface::class);
        if ($user && ! $profileService->isProtectedSeedAccount($user)) {
            $rules['email'] = ['required', 'string', 'email', 'max:255', 'unique:users,email,'.$user->id];
        }

        return $rules;
    }
}
