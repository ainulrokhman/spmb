<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One WhatsApp send attempt and its outcome. Written by the
 * WhatsApp sender service for every message (automatic, OTP,
 * and admin test sends alike) and read by the WhatsApp report.
 */
class WhatsappMessageLog extends Model
{
    public const TYPE_TEST = 'test';

    public const TYPE_MANUAL = 'manual';

    public const TYPE_OTP = 'otp';

    public const TYPE_REGISTRATION = 'pendaftaran_berhasil';

    public const TYPE_PAYMENT = 'pembayaran';

    public const TYPE_REMINDER = 'pengingat';

    public const TYPE_RESULT = 'pengumuman';

    public const TYPE_DECISION = 'keputusan';

    public const TYPES = [
        self::TYPE_TEST => 'Pesan Uji',
        self::TYPE_MANUAL => 'Manual',
        self::TYPE_OTP => 'OTP',
        self::TYPE_REGISTRATION => 'Pendaftaran Berhasil',
        self::TYPE_PAYMENT => 'Pembayaran',
        self::TYPE_REMINDER => 'Pengingat Tagihan',
        self::TYPE_RESULT => 'Pengumuman Hasil',
        self::TYPE_DECISION => 'Keputusan Keringanan/Cicilan',
    ];

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUSES = [
        self::STATUS_SENT => 'Terkirim',
        self::STATUS_FAILED => 'Gagal',
        self::STATUS_SKIPPED => 'Dilewati',
    ];

    protected $fillable = [
        'whatsapp_device_id',
        'school_unit_id',
        'recipient_phone',
        'recipient_name',
        'type',
        'message',
        'status',
        'error',
        'provider_message_id',
    ];

    /**
     * The device that actually sent (or was chosen for) this
     * message; null when no device was available at all.
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(WhatsappDevice::class, 'whatsapp_device_id');
    }

    /**
     * The unit the message belongs to (the applicant's unit),
     * regardless of which device ended up sending it.
     */
    public function schoolUnit(): BelongsTo
    {
        return $this->belongsTo(SchoolUnit::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Whether the foundation fallback device sent a message that
     * belonged to a unit (the unit's own device was unavailable).
     */
    public function sentViaFoundationFallback(): bool
    {
        return $this->school_unit_id !== null
            && $this->device !== null
            && $this->device->isFoundation();
    }
}
