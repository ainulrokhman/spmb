<?php

namespace App\Models;

use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * Payment statuses, uniform across every channel (Bab 6.4).
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'pending' => 'Menunggu Pembayaran',
        'awaiting_confirmation' => 'Menunggu Konfirmasi',
        'paid' => 'Lunas',
        'expired' => 'Kedaluwarsa',
        'failed' => 'Gagal',
    ];

    /**
     * Payment channel keys and their display labels.
     *
     * @var array<string, string>
     */
    public const CHANNELS = [
        'manual' => 'Manual',
        'midtrans' => 'Midtrans',
        'bsi' => 'BSI',
    ];

    protected $fillable = [
        'channel',
        'amount',
        'status',
        'reference',
        'proof_path',
        'snap_token',
        'paid_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function channelLabel(): string
    {
        return self::CHANNELS[$this->channel] ?? $this->channel;
    }
}
