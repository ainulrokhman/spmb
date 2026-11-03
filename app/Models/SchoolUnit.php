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
        'selection_enabled',
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
        'selection_enabled' => 'boolean',
    ];

    /**
     * The program this unit runs.
     */
    /**
     * WhatsApp chat link (wa.me) built from the unit's phone
     * number, or null when the number is not a valid mobile.
     */
    public function whatsappUrl(): ?string
    {
        $normalized = \App\Support\PhoneNumber::normalize($this->phone);

        return $normalized === null
            ? null
            : 'https://wa.me/62'.substr($normalized, 1);
    }

    /**
     * Google Maps search link for the unit's address, or null
     * when the unit has no address yet.
     */
    public function mapsUrl(): ?string
    {
        if (blank($this->address)) {
            return null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.urlencode($this->name.' '.$this->address);
    }

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
    /**
     * Selection point rules configured for this unit.
     */
    public function selectionRules(): HasMany
    {
        return $this->hasMany(SelectionRule::class);
    }

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
