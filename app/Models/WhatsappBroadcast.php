<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One admin-composed WhatsApp broadcast to many guardians.
 * Recipients are snapshotted at creation time; sending walks
 * them one by one through a chained job so the broadcast can
 * be paused, resumed, or cancelled between messages.
 */
class WhatsappBroadcast extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING => 'Menunggu',
        self::STATUS_SENDING => 'Mengirim',
        self::STATUS_PAUSED => 'Jeda',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_CANCELLED => 'Dibatalkan',
    ];

    protected $fillable = [
        'school_unit_id',
        'admission_wave_id',
        'application_status',
        'payment_filter',
        'message',
        'delay_min',
        'delay_max',
        'status',
        'total_recipients',
        'sent_count',
        'failed_count',
        'created_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'delay_min' => 'integer',
            'delay_max' => 'integer',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function schoolUnit(): BelongsTo
    {
        return $this->belongsTo(SchoolUnit::class);
    }

    public function admissionWave(): BelongsTo
    {
        return $this->belongsTo(AdmissionWave::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsappBroadcastRecipient::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /** Processed share of the recipient list, for progress bars. */
    public function progressPercent(): int
    {
        if ($this->total_recipients === 0) {
            return 0;
        }

        return (int) floor(($this->sent_count + $this->failed_count) / $this->total_recipients * 100);
    }
}
