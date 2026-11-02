<?php

namespace App\Http\Requests\Guardian;

use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\Major;
use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Account registration that also starts the first application:
 * validates the guardian account data together with the child's
 * starting data (name, relation, unit, wave, track) so both are
 * created in one submission.
 */
class RegisterGuardianRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the phone number into the field that is validated
     * and stored.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_normalized' => PhoneNumber::normalize($this->input('phone')),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone_normalized' => ['required', Rule::unique('users', 'phone')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'child_name' => ['required', 'string', 'max:255'],
            'guardian_relation' => ['required', Rule::in(array_keys(Application::GUARDIAN_RELATIONS))],
            'school_unit_id' => ['required', 'integer', 'exists:school_units,id'],
            'admission_wave_id' => ['nullable', 'integer', 'exists:admission_waves,id'],
            'admission_track_id' => ['required', 'integer', 'exists:admission_tracks,id'],
            'major_id' => ['nullable', 'integer', 'exists:majors,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone_normalized.required' => 'Nomor HP tidak valid atau tidak boleh kosong.',
            'phone_normalized.unique' => 'Nomor HP sudah terdaftar. Silakan masuk dengan nomor tersebut.',
        ];
    }

    /**
     * Cross-checks: resolve the wave (the unit's single open wave
     * when none was chosen), then the same consistency checks as
     * starting an application.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $unitId = (int) $this->input('school_unit_id');
            $waveId = $this->input('admission_wave_id');

            if (empty($waveId)) {
                $openWaves = AdmissionWave::query()
                    ->where('school_unit_id', $unitId)
                    ->get()
                    ->filter(fn (AdmissionWave $wave) => $wave->status() === AdmissionWave::STATUS_OPEN)
                    ->values();

                if ($openWaves->count() !== 1) {
                    $validator->errors()->add('admission_wave_id', 'Pilih gelombang pendaftaran yang sedang dibuka untuk unit ini.');

                    return;
                }

                $waveId = $openWaves->first()->id;
                $this->merge(['admission_wave_id' => $waveId]);
            }

            $wave = AdmissionWave::with('admissionTracks')->find($waveId);

            if ($wave === null) {
                return;
            }

            if ((int) $wave->school_unit_id !== $unitId) {
                $validator->errors()->add('admission_wave_id', 'Gelombang tidak dimiliki unit yang dipilih.');
            } elseif ($wave->status() !== AdmissionWave::STATUS_OPEN) {
                $validator->errors()->add('admission_wave_id', 'Gelombang ini tidak sedang dibuka.');
            }

            if (! $wave->admissionTracks->contains('id', (int) $this->input('admission_track_id'))) {
                $validator->errors()->add('admission_track_id', 'Jalur ini tidak dibuka pada gelombang yang dipilih.');
            }

            if (! empty($this->input('major_id'))) {
                $major = Major::find((int) $this->input('major_id'));

                if ($major !== null && (int) $major->school_unit_id !== $unitId) {
                    $validator->errors()->add('major_id', 'Jurusan tidak dimiliki unit yang dipilih.');
                }
            }
        });
    }
}
