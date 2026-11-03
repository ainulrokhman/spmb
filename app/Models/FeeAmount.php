<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeAmount extends Model
{
    use HasFactory;

    /**
     * Gender codes an amount can be restricted to (null = all genders).
     *
     * @var array<string, string>
     */
    public const GENDERS = [
        'L' => 'Laki-laki',
        'P' => 'Perempuan',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'fee_id',
        'admission_track_id',
        'child_status_id',
        'gender',
        'amount',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * The fee mapping this amount belongs to.
     */
    public function fee(): BelongsTo
    {
        return $this->belongsTo(Fee::class);
    }

    /**
     * The admission track this amount is restricted to, if any.
     */
    public function admissionTrack(): BelongsTo
    {
        return $this->belongsTo(AdmissionTrack::class);
    }

    /**
     * The child status this amount is restricted to, if any.
     */
    public function childStatus(): BelongsTo
    {
        return $this->belongsTo(ChildStatus::class);
    }

    /**
     * Specificity rank used by most-specific-first resolution
     * across the three scope dimensions (child status, track,
     * gender). More specified dimensions win; among equally
     * many, a status-scoped amount beats a non-status one:
     * status+track+gender > status+track > status+gender >
     * track+gender > status > track > gender > global.
     */
    public function specificity(): int
    {
        $mask = ($this->child_status_id ? 4 : 0)
            + ($this->admission_track_id ? 2 : 0)
            + ($this->gender ? 1 : 0);

        return [0 => 1, 1 => 2, 2 => 3, 4 => 4, 3 => 5, 5 => 6, 6 => 7, 7 => 8][$mask];
    }
}
