<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdmissionWave extends Model
{
    use HasFactory;

    /** Status key: the wave is switched off manually. */
    public const STATUS_INACTIVE = 'inactive';

    /** Status key: the wave is active but its opening time has not come. */
    public const STATUS_UPCOMING = 'upcoming';

    /** Status key: the wave is active and inside its open period. */
    public const STATUS_OPEN = 'open';

    /** Status key: the wave is active but its closing time has passed. */
    public const STATUS_CLOSED = 'closed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_unit_id',
        'academic_year_id',
        'name',
        'quota',
        'opens_at',
        'closes_at',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quota' => 'integer',
        'opens_at' => 'datetime',
        'closes_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * The school unit that owns this wave.
     */
    public function schoolUnit(): BelongsTo
    {
        return $this->belongsTo(SchoolUnit::class);
    }

    /**
     * The academic year this wave belongs to.
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * The admission tracks opened by this wave.
     */
    public function admissionTracks(): BelongsToMany
    {
        return $this->belongsToMany(AdmissionTrack::class, 'admission_wave_track')->withTimestamps();
    }

    /**
     * The computed status of the wave.
     *
     * The manual switch (is_active) overrides the schedule: a switched
     * off wave is never open. Otherwise the open period decides.
     */
    public function status(): string
    {
        if (! $this->is_active) {
            return self::STATUS_INACTIVE;
        }

        $now = now();

        if ($now->lt($this->opens_at)) {
            return self::STATUS_UPCOMING;
        }

        if ($now->gt($this->closes_at)) {
            return self::STATUS_CLOSED;
        }

        return self::STATUS_OPEN;
    }

    /**
     * Whether the wave currently accepts registrations.
     */
    public function isCurrentlyOpen(): bool
    {
        return $this->status() === self::STATUS_OPEN;
    }
}
