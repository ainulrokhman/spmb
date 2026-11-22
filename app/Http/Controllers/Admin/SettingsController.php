<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Setting;
use App\Services\PaymentService;
use App\Support\RegistrationNumberFormatter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    /**
     * Show the settings page. The first section configures the
     * payment channels guardians can choose (Bab 6.4); secrets stay
     * in .env and are only reported as configured or not.
     */
    public function edit(PaymentService $payments)
    {
        $settings = [
            'foundation_name' => Setting::get('foundation_name', ''),
            'foundation_tagline' => Setting::get('foundation_tagline', ''),
            'site_logo' => Setting::get('site_logo'),
            'profile_video_url' => Setting::get('profile_video_url', ''),
            'payment_manual_enabled' => Setting::flag('payment_manual_enabled', true),
            'payment_manual_instructions' => Setting::get('payment_manual_instructions', ''),
            'payment_midtrans_enabled' => Setting::flag('payment_midtrans_enabled', false),
            'registration_number_pattern' => RegistrationNumberFormatter::pattern(),
        ];

        $midtransConfigured = ! empty(config('midtrans.server_key')) && ! empty(config('midtrans.client_key'));

        $activeYear = AcademicYear::query()->where('is_active', true)->first();
        $registrationPreview = RegistrationNumberFormatter::render(
            $settings['registration_number_pattern'],
            '31',
            (string) ($activeYear->name ?? '2026/2027'),
            1,
        );

        return view('admin.settings.edit', compact('settings', 'midtransConfigured', 'registrationPreview'));
    }

    /**
     * Persist the settings.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'foundation_name' => ['nullable', 'string', 'max:120'],
            'foundation_tagline' => ['nullable', 'string', 'max:200'],
            'site_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,svg,webp', 'max:2048'],
            'profile_video_url' => ['nullable', 'url', 'max:500'],
            'payment_manual_instructions' => ['nullable', 'string', 'max:2000'],
            'registration_number_pattern' => ['nullable', 'string', function (string $attribute, mixed $value, callable $fail) {
                $error = RegistrationNumberFormatter::validatePattern((string) $value);
                if ($error !== null) {
                    $fail($error);
                }
            }],
        ]);

        Setting::set('foundation_name', $data['foundation_name'] ?? null);
        Setting::set('foundation_tagline', $data['foundation_tagline'] ?? null);
        Setting::set('profile_video_url', $data['profile_video_url'] ?? null);

        // The logo is optional: uploading replaces the stored
        // file, and the remove checkbox clears it entirely.
        if ($request->boolean('remove_logo')) {
            $this->deleteLogo();
        } elseif ($request->hasFile('site_logo')) {
            $this->deleteLogo();

            $path = $request->file('site_logo')->store('branding', 'public');
            Setting::set('site_logo', $path);
        }

        Setting::set('payment_manual_enabled', $request->boolean('payment_manual_enabled') ? '1' : '0');
        Setting::set('payment_manual_instructions', $data['payment_manual_instructions'] ?? null);
        Setting::set('payment_midtrans_enabled', $request->boolean('payment_midtrans_enabled') ? '1' : '0');
        // Only the settings form sends the pattern; other posts
        // to this endpoint (branding, payment channels) leave a
        // stored pattern untouched. An emptied field resets to
        // the default pattern.
        if ($request->has('registration_number_pattern')) {
            $pattern = trim((string) ($data['registration_number_pattern'] ?? ''));
            Setting::set('registration_number_pattern', $pattern !== '' ? $pattern : null);
        }

        return redirect()->route('admin.settings.edit')
            ->with('success', 'Pengaturan berhasil disimpan.');
    }

    /**
     * Delete the currently stored logo file and setting.
     */
    private function deleteLogo(): void
    {
        $current = Setting::get('site_logo');

        if ($current !== null && $current !== '') {
            Storage::disk('public')->delete($current);
        }

        Setting::set('site_logo', null);
    }
}
