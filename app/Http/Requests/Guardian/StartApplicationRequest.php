<?php

namespace App\Http\Requests\Guardian;

use App\Models\AdmissionWave;
use App\Models\Major;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StartApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'school_unit_id' => ['required', 'integer', 'exists:school_units,id'],
            'admission_wave_id' => ['required', 'integer', 'exists:admission_waves,id'],
            'admission_track_id' => ['required', 'integer', 'exists:admission_tracks,id'],
            'major_id' => ['nullable', 'integer', 'exists:majors,id'],
        ];
    }

    /**
     * Cross-checks: the wave belongs to the unit and is open, the
     * track is opened by the wave, and the major belongs to the unit.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $wave = AdmissionWave::with('admissionTracks')->find($this->input('admission_wave_id'));

            if ($wave === null) {
                return;
            }

            if ((int) $wave->school_unit_id !== (int) $this->input('school_unit_id')) {
                $validator->errors()->add('admission_wave_id', 'Gelombang tidak dimiliki unit yang dipilih.');
            } elseif ($wave->status() !== AdmissionWave::STATUS_OPEN) {
                $validator->errors()->add('admission_wave_id', 'Gelombang ini tidak sedang dibuka.');
            }

            if (! $wave->admissionTracks->contains('id', (int) $this->input('admission_track_id'))) {
                $validator->errors()->add('admission_track_id', 'Jalur ini tidak dibuka pada gelombang yang dipilih.');
            }

            if ($this->filled('major_id')) {
                $majorBelongs = Major::query()
                    ->whereKey($this->input('major_id'))
                    ->where('school_unit_id', $this->input('school_unit_id'))
                    ->exists();

                if (! $majorBelongs) {
                    $validator->errors()->add('major_id', 'Jurusan tidak dimiliki unit yang dipilih.');
                }
            }
        });
    }
}
