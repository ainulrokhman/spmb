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
     * Specificity rank used by most-specific-first resolution:
     * track+gender (3) beats track (2), gender (1), and global (0).
     */
    public function specificity(): int
    {
        return ($this->admission_track_id ? 2 : 0) + ($this->gender ? 1 : 0);
    }
}
