<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A WhatsApp sender device: one per school unit, or the single
 * foundation-level device (school_unit_id null) shared by every
 * unit without its own number and used as their fallback.
 *
 * Providers:
 * - gowa: a device slot on the foundation's own GOWA server,
 *   addressed by device_id; the server credentials live in .env.
 * - fonnte: a device on the Fonnte cloud, addressed by its
 *   device token; the token is a credential, stored encrypted
 *   and never displayed in full.
 */
class WhatsappDevice extends Model
{
    public const PROVIDER_GOWA = 'gowa';

    public const PROVIDER_FONNTE = 'fonnte';

    public const PROVIDERS = [
        self::PROVIDER_GOWA => 'GOWA (server sendiri)',
        self::PROVIDER_FONNTE => 'Fonnte (cloud)',
    ];

    public const STATUS_CONNECTED = 'connected';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_UNKNOWN = 'unknown';

    public const STATUSES = [
        self::STATUS_CONNECTED => 'Terhubung',
        self::STATUS_DISCONNECTED => 'Terputus',
        self::STATUS_UNKNOWN => 'Belum diketahui',
    ];

    protected $fillable = [
        'school_unit_id',
        'provider',
        'device_id',
        'token',
        'display_name',
        'phone',
        'status',
        'last_checked_at',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'token' => 'encrypted',
        'last_checked_at' => 'datetime',
    ];

    /**
     * The unit this device belongs to; null for the foundation
     * device.
     */
    public function schoolUnit(): BelongsTo
    {
        return $this->belongsTo(SchoolUnit::class);
    }

    /**
     * Send attempts made through this device.
     */
    public function messageLogs(): HasMany
    {
        return $this->hasMany(WhatsappMessageLog::class);
    }

    /**
     * The foundation-level device (at most one; enforced by the
     * management controller because NULLs cannot be unique).
     */
    public static function foundation(): ?self
    {
        return static::query()->whereNull('school_unit_id')->first();
    }

    /**
     * The device registered for one unit, if any.
     */
    public static function forUnit(int $schoolUnitId): ?self
    {
        return static::query()->where('school_unit_id', $schoolUnitId)->first();
    }

    public function isFoundation(): bool
    {
        return $this->school_unit_id === null;
    }

    public function isConnected(): bool
    {
        return $this->status === self::STATUS_CONNECTED;
    }

    public function providerLabel(): string
    {
        return self::PROVIDERS[$this->provider] ?? $this->provider;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    /**
     * Human label for lists: the admin-given name, else the
     * connected number, else the provider's identifier.
     */
    public function label(): string
    {
        return $this->display_name ?: ($this->phone ?: ($this->device_id ?: $this->providerLabel()));
    }
}
