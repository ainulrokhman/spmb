<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChildStatus extends Model
{
    use HasFactory;

    /**
     * The name of the default status every application falls
     * back to when none was chosen.
     */
    public const DEFAULT_NAME = 'Reguler';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
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
     * Applications carrying this status.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
