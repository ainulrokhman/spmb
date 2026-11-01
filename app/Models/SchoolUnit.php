<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolUnit extends Model
{
    use HasFactory;

    /**
     * Available school levels (jenjang).
     *
     * @var array<int, string>
     */
    public const LEVELS = [
        'PAUD/TK',
        'SD/MI',
        'SMP/MTs',
        'SMA',
        'SMK',
        'MA',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'level',
        'program_id',
        'form_fee',
        'address',
        'phone',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'form_fee' => 'integer',
    ];

    /**
     * The program this unit runs.
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Admission tracks owned by this unit.
     */
    public function admissionTracks(): HasMany
    {
        return $this->hasMany(AdmissionTrack::class);
    }

    /**
     * Admission waves owned by this unit.
     */
    public function admissionWaves(): HasMany
    {
        return $this->hasMany(AdmissionWave::class);
    }

    /**
     * Fee mappings defined for this unit.
     */
    public function fees(): HasMany
    {
        return $this->hasMany(Fee::class);
    }

    /**
     * Dynamic form fields defined for this unit.
     */
    public function formFields(): HasMany
    {
        return $this->hasMany(FormField::class);
    }

    /**
     * Document requirements defined for this unit.
     */
    public function documentRequirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class);
    }

    /**
     * Majors owned by this unit.
     */
    public function majors(): HasMany
    {
        return $this->hasMany(Major::class);
    }
}
