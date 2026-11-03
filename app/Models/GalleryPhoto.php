<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One photo of the landing page gallery slide, managed from
 * the admin Galeri menu. Inactive photos stay stored but are
 * never shown publicly.
 */
class GalleryPhoto extends Model
{
    /** @use HasFactory<\Database\Factories\GalleryPhotoFactory> */
    use HasFactory;

    protected $fillable = [
        'file_path',
        'caption',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Active photos in display order (landing carousel).
     */
    public function scopeShown(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Public URL of the stored photo.
     */
    public function url(): string
    {
        return asset('storage/'.$this->file_path);
    }
}
