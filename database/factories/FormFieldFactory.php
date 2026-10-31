<?php

namespace Database\Factories;

use App\Models\FormField;
use App\Models\SchoolUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormField>
 */
class FormFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(FormField::TYPES);

        return [
            'school_unit_id' => SchoolUnit::factory(),
            'key' => fake()->unique()->lexify('field_????'),
            'label' => fake()->words(2, true),
            'type' => $type,
            'options' => in_array($type, FormField::CHOICE_TYPES, true)
                ? fake()->randomElements(['A', 'B', 'C', 'D'], 3)
                : null,
            'is_required' => fake()->boolean(),
            'section' => fake()->randomElement(['Data Siswa', 'Data Orang Tua', null]),
            'sort_order' => fake()->numberBetween(0, 20),
            'help_text' => null,
            'is_active' => true,
        ];
    }
}
