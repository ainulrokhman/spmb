<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\PaymentService;
use Illuminate\Http\Request;

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
            'payment_manual_enabled' => Setting::flag('payment_manual_enabled', true),
            'payment_manual_instructions' => Setting::get('payment_manual_instructions', ''),
            'payment_midtrans_enabled' => Setting::flag('payment_midtrans_enabled', false),
        ];

        $midtransConfigured = ! empty(config('midtrans.server_key')) && ! empty(config('midtrans.client_key'));

        return view('admin.settings.edit', compact('settings', 'midtransConfigured'));
    }

    /**
     * Persist the settings.
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'payment_manual_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        Setting::set('payment_manual_enabled', $request->boolean('payment_manual_enabled') ? '1' : '0');
        Setting::set('payment_manual_instructions', $data['payment_manual_instructions'] ?? null);
        Setting::set('payment_midtrans_enabled', $request->boolean('payment_midtrans_enabled') ? '1' : '0');

        return redirect()->route('admin.settings.edit')
            ->with('success', 'Pengaturan berhasil disimpan.');
    }
}
