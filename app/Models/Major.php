<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Major extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'school_unit_id',
        'name',
        'code',
        'is_active',
        'quota',
        'quota_putra',
        'quota_putri',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'quota' => 'integer',
        'quota_putra' => 'integer',
        'quota_putri' => 'integer',
    ];

    /**
     * The school unit that owns this major.
     */
    public function schoolUnit(): BelongsTo
    {
        return $this->belongsTo(SchoolUnit::class);
    }

    public function applications(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Application::class);
    }

    /** Human-readable quota summary, e.g. "30 (Putra 12 · Putri 13)". */
    public function quotaLabel(): string
    {
        if ($this->quota === null && $this->quota_putra === null && $this->quota_putri === null) {
            return 'Tanpa batas';
        }

        $parts = [];
        if ($this->quota !== null) {
            $parts[] = (string) $this->quota;
        }
        if ($this->quota_putra !== null) {
            $parts[] = 'Putra '.$this->quota_putra;
        }
        if ($this->quota_putri !== null) {
            $parts[] = 'Putri '.$this->quota_putri;
        }

        return implode(' · ', $parts);
    }
}
