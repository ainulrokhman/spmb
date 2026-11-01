<?php

namespace Database\Factories;

use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $wave = AdmissionWave::factory();

        return [
            'user_id' => User::factory(),
            'school_unit_id' => SchoolUnit::factory(),
            'admission_wave_id' => $wave,
            'admission_track_id' => AdmissionTrack::factory(),
            'academic_year_id' => fn (array $attributes) => AdmissionWave::find($attributes['admission_wave_id'])?->academic_year_id,
            'form_fee_amount' => 0,
            'form_fee_status' => 'free',
            'status' => 'filling_form',
        ];
    }

    /**
     * An application still waiting for its form fee to be settled.
     */
    public function awaitingFee(int $amount = 50000): static
    {
        return $this->state(fn (array $attributes) => [
            'form_fee_amount' => $amount,
            'form_fee_status' => 'unpaid',
            'status' => 'awaiting_form_fee',
        ]);
    }

    /**
     * An application whose form has been filled, ready for documents.
     */
    public function formFilled(): static
    {
        return $this->state(fn (array $attributes) => [
            'child_name' => fake()->name(),
            'birth_place' => fake()->city(),
            'birth_date' => fake()->dateTimeBetween('-12 years', '-6 years')->format('Y-m-d'),
            'gender' => 'L',
            'parent_name' => fake()->name(),
            'parent_phone' => '081234567890',
            'guardian_relation' => 'ayah',
            'father_data' => ['name' => fake()->name(), 'status' => 'Masih Hidup'],
            'mother_data' => ['name' => fake()->name(), 'status' => 'Masih Hidup'],
            'status' => 'uploading_documents',
        ]);
    }
}
