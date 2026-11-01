<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PhoneNumber;
use App\Support\SystemAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/admin');
        }

        return view('auth.login');
    }

    /**
     * Handle the login request.
     */
    public function login(Request $request)
    {
        // Staff sign in with an email address; guardians (Wali) sign in
        // with their phone number. The form submits one `login` field;
        // `email` is still accepted for compatibility.
        $field = $request->filled('login') ? 'login' : 'email';
        $identifier = trim((string) $request->input($field, ''));

        $rules = [
            $field => ['required'],
            'password' => ['required'],
        ];

        $isEmail = str_contains($identifier, '@') || $field === 'email';
        if ($isEmail) {
            $rules[$field][] = 'email';
        }
        $request->validate($rules);

        $credentials = $isEmail
            ? ['email' => $identifier, 'password' => $request->input('password')]
            : ['phone' => PhoneNumber::normalize($identifier), 'password' => $request->input('password')];

        $remember = $request->has('remember');

        // A null phone means the number was not a valid mobile number;
        // skip the attempt and fall through to the failure below.
        $attemptable = $isEmail || $credentials['phone'] !== null;

        if ($attemptable) {
            if (Auth::attempt($credentials, $remember)) {
                $request->session()->regenerate();

                /** @var User $user */
                $user = Auth::user();
                $home = $user->hasRole(SystemAccess::WALI_ROLE)
                    ? route('guardian.dashboard')
                    : '/admin';

                return redirect()->intended($home)
                    ->with('success', 'Selamat datang kembali, '.$user->name.'!');
            }
        }

        throw ValidationException::withMessages([
            $field => __('auth.failed'),
        ]);
    }

    /**
     * Handle the logout request.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('success', 'Berhasil keluar.');
    }
}
