<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A school unit's total intake cap for one academic year,
 * counted across all of the unit's waves in that year. No row
 * for a year means that year is unlimited for the unit.
 */
class UnitQuota extends Model
{
    use HasFactory;

    protected $fillable = [
        'school_unit_id',
        'academic_year_id',
        'quota',
    ];

    protected function casts(): array
    {
        return [
            'quota' => 'integer',
        ];
    }

    public function schoolUnit(): BelongsTo
    {
        return $this->belongsTo(SchoolUnit::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
