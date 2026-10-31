<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fee extends Model
{
    use HasFactory;

    /**
     * How a mapped component behaves at re-registration time.
     *
     * @var array<int, string>
     */
    public const MODES = [
        'fixed',
        'pilihan',
        'bebas',
    ];

    /**
     * Mode names used across the fee services.
     */
    public const MODE_FIXED = 'fixed';

    public const MODE_PILIHAN = 'pilihan';

    public const MODE_BEBAS = 'bebas';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_unit_id',
        'fee_component_id',
        'mode',
        'min_amount',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'min_amount' => 'integer',
    ];

    /**
     * The school unit this mapping belongs to.
     */
    public function schoolUnit(): BelongsTo
    {
        return $this->belongsTo(SchoolUnit::class);
    }

    /**
     * The shared fee component being mapped.
     */
    public function feeComponent(): BelongsTo
    {
        return $this->belongsTo(FeeComponent::class);
    }

    /**
     * Amounts and tiers defined for this mapping.
     */
    public function amounts(): HasMany
    {
        return $this->hasMany(FeeAmount::class);
    }
}
