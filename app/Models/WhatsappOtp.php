<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One WhatsApp one-time code sent to a phone number
 * (WhatsApp Tahap 2). The code itself is never stored —
 * only its hash — mirroring the OTP rules agreed in the
 * plan: 5-minute validity, a limited number of attempts,
 * and a resend cooldown enforced by the service.
 */
class WhatsappOtp extends Model
{
    public const PURPOSE_REGISTRATION = 'registration';

    protected $fillable = [
        'phone',
        'purpose',
        'code_hash',
        'attempts',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
