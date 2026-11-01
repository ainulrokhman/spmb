<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\SystemAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class GuardianAuthController extends Controller
{
    /**
     * Show the guardian registration form.
     */
    public function showRegister()
    {
        return view('auth.register');
    }

    /**
     * Register a new guardian (Wali) account.
     *
     * Guardians identify themselves with a normalized phone number,
     * which becomes their login identity alongside the password.
     */
    public function register(Request $request)
    {
        $phone = PhoneNumber::normalize($request->input('phone'));

        $request->merge(['phone_normalized' => $phone]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone_normalized' => [
                'required',
                Rule::unique('users', 'phone'),
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'phone_normalized.required' => 'Nomor HP tidak valid atau tidak boleh kosong.',
            'phone_normalized.unique' => 'Nomor HP sudah terdaftar. Silakan masuk dengan nomor tersebut.',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone_normalized'],
            'password' => $data['password'],
        ]);
        $user->assignRole(SystemAccess::WALI_ROLE);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('guardian.dashboard')
            ->with('success', 'Pendaftaran akun berhasil. Selamat datang, '.$user->name.'!');
    }
}
