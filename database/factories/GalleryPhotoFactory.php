<?php

namespace Database\Factories;

use App\Models\GalleryPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GalleryPhoto>
 */
class GalleryPhotoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_path' => 'galeri/contoh-'.fake()->unique()->numberBetween(1, 99999).'.jpg',
            'caption' => fake()->sentence(4),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
