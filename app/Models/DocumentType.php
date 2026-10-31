<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'allowed_extensions',
        'max_size_kb',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'max_size_kb' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Per-unit requirements defined for this document type.
     */
    public function documentRequirements(): HasMany
    {
        return $this->hasMany(DocumentRequirement::class);
    }

    /**
     * The allowed extensions as a list of lowercase strings.
     *
     * @return array<int, string>
     */
    public function extensionList(): array
    {
        return collect(explode(',', $this->allowed_extensions))
            ->map(fn (string $extension) => strtolower(trim($extension)))
            ->filter()
            ->values()
            ->all();
    }
}
