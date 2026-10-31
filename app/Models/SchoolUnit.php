<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
     * Available programs.
     *
     * @var array<int, string>
     */
    public const PROGRAMS = [
        'Reguler',
        'Boarding',
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
        'program',
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
    ];

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
