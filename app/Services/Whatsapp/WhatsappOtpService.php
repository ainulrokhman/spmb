<?php

namespace App\Services\Whatsapp;

use App\Models\Setting;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappOtp;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;

/**
 * WhatsApp OTP for guardian registration (WhatsApp Tahap 2,
 * modelled on psb-laravel's proven OtpService with SPMB's
 * structural fixes: the code is stored hashed, it is sent
 * through the registering unit's device chain with the
 * foundation fallback, and its text comes from the editable
 * OTP template instead of a hardcoded string.
 *
 * Rules from the plan: 6-digit code, valid 5 minutes, at
 * most 5 verification attempts, 60-second resend cooldown,
 * and a Settings kill switch (whatsapp_otp_enabled, default
 * off) so registration keeps working exactly as before until
 * an admin turns OTP on.
 */
class WhatsappOtpService
{
    public const EXPIRY_MINUTES = 5;

    public const MAX_ATTEMPTS = 5;

    public const COOLDOWN_SECONDS = 60;

    public function __construct(
        private readonly WhatsappSender $sender,
    ) {}

    /**
     * Whether registration currently demands an OTP code.
     */
    public static function isRequired(): bool
    {
        return Setting::flag('whatsapp_otp_enabled', false);
    }

    /**
     * Generate and send a registration code to the phone.
     * The phone may arrive in any typed form; it is stored
     * and compared in the normalized 08… form, the same form
     * guardian accounts use as their identity.
     *
     * @return array{success: bool, message: string, expires_at?: string}
     */
    public function send(string $phone, ?int $schoolUnitId, ?string $recipientName = null): array
    {
        $normalized = PhoneNumber::normalize($phone);
        if ($normalized === null) {
            return ['success' => false, 'message' => 'Nomor HP tidak valid. Periksa kembali nomor WhatsApp Anda.'];
        }

        $recent = WhatsappOtp::query()
            ->where('phone', $normalized)
            ->where('purpose', WhatsappOtp::PURPOSE_REGISTRATION)
            ->latest('id')
            ->first();

        if ($recent !== null && $recent->created_at->gt(now()->subSeconds(self::COOLDOWN_SECONDS))) {
            $wait = self::COOLDOWN_SECONDS - $recent->created_at->diffInSeconds(now());

            return ['success' => false, 'message' => 'Kode baru saja dikirim. Tunggu '.$wait.' detik sebelum meminta kode lagi.'];
        }

        // A fresh code replaces any still-unverified one.
        WhatsappOtp::query()
            ->where('phone', $normalized)
            ->where('purpose', WhatsappOtp::PURPOSE_REGISTRATION)
            ->whereNull('verified_at')
            ->delete();

        $code = (string) random_int(100000, 999999);

        $otp = WhatsappOtp::create([
            'phone' => $normalized,
            'purpose' => WhatsappOtp::PURPOSE_REGISTRATION,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::EXPIRY_MINUTES),
        ]);

        $log = $this->sender->sendTemplated($schoolUnitId, $normalized, WhatsappMessageLog::TYPE_OTP, [
            'nama' => $recipientName ?? '',
            'kode_otp' => $code,
            'berlaku_menit' => self::EXPIRY_MINUTES,
        ], $recipientName);

        if ($log->status !== WhatsappMessageLog::STATUS_SENT) {
            // An undelivered code must not be verifiable.
            $otp->delete();

            return ['success' => false, 'message' => 'Kode verifikasi belum dapat dikirim: '.($log->error ?? 'tidak ada device WhatsApp yang terhubung.')];
        }

        return [
            'success' => true,
            'message' => 'Kode verifikasi telah dikirim ke WhatsApp '.$normalized.'. Kode berlaku '.self::EXPIRY_MINUTES.' menit.',
            'expires_at' => $otp->expires_at->toIso8601String(),
        ];
    }

    /**
     * Verify a code for the phone. A correct code is consumed
     * (marked verified) so it cannot be replayed; a wrong code
     * spends one of the row's attempts.
     */
    public function verify(string $phone, string $code): bool
    {
        $normalized = PhoneNumber::normalize($phone);
        if ($normalized === null) {
            return false;
        }

        $otp = WhatsappOtp::query()
            ->where('phone', $normalized)
            ->where('purpose', WhatsappOtp::PURPOSE_REGISTRATION)
            ->whereNull('verified_at')
            ->latest('id')
            ->first();

        if ($otp === null || $otp->isExpired() || $otp->attempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return false;
        }

        $otp->update(['verified_at' => now()]);

        return true;
    }

    /**
     * Delete codes whose validity has passed; run daily by
     * the scheduler (whatsapp:cleanup-otps).
     */
    public function cleanupExpired(): int
    {
        return WhatsappOtp::query()->where('expires_at', '<', now())->delete();
    }
}
