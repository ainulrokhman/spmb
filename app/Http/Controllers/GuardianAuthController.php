<?php

namespace App\Http\Controllers;

use App\Contracts\ApplicationServiceInterface;
use App\Http\Requests\Guardian\RegisterGuardianRequest;
use App\Models\AdmissionWave;
use App\Models\SchoolUnit;
use App\Models\User;
use App\Support\SystemAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GuardianAuthController extends Controller
{
    public function __construct(
        private readonly ApplicationServiceInterface $applications,
    ) {}

    /**
     * Show the guardian registration form, with the unit / wave
     * cascade used to start the first application right away.
     */
    public function showRegister(Request $request)
    {
        $units = SchoolUnit::query()
            ->with(['program', 'majors' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $selectedUnit = $units->firstWhere('id', (int) (old('school_unit_id') ?? $request->query('unit')));

        $waves = $selectedUnit === null
            ? collect()
            : $selectedUnit->admissionWaves()
                ->with(['academicYear', 'admissionTracks'])
                ->orderBy('opens_at')
                ->get()
                ->filter(fn (AdmissionWave $wave) => $wave->status() === AdmissionWave::STATUS_OPEN)
                ->values();

        $selectedWave = $waves->firstWhere('id', (int) (old('admission_wave_id') ?? $request->query('wave')))
            ?? ($waves->count() === 1 ? $waves->first() : null);

        return view('auth.register', compact('units', 'selectedUnit', 'waves', 'selectedWave'));
    }

    /**
     * Register a new guardian (Wali) account and immediately start
     * the first child's application with the starting data given
     * on the same form. Guardians identify themselves with a
     * normalized phone number, which becomes their login identity
     * alongside the password. Account and application are created
     * in one transaction, after full validation.
     */
    public function register(RegisterGuardianRequest $request)
    {
        $data = $request->validated();
        // The resolved wave id comes from the request input: the
        // form request merges the auto-picked wave there during
        // validation, after validated() data was snapshotted.
        $waveId = $request->input('admission_wave_id');

        [$user, $application] = DB::transaction(function () use ($data, $waveId) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone_normalized'],
                'password' => $data['password'],
            ]);
            $user->assignRole(SystemAccess::WALI_ROLE);

            $wave = AdmissionWave::findOrFail($waveId);
            $track = $wave->admissionTracks()->findOrFail($data['admission_track_id']);
            $major = ! empty($data['major_id'])
                ? $wave->schoolUnit->majors()->findOrFail($data['major_id'])
                : null;

            $application = $this->applications->start(
                $user,
                $wave,
                $track,
                $major,
                $data['child_name'],
                $data['guardian_relation'],
            );

            return [$user, $application];
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('guardian.applications.show', $application)
            ->with('success', 'Pendaftaran akun berhasil. Selamat datang, '.$user->name.'! Pendaftaran untuk '.$application->child_name.' telah dimulai — selesaikan tahap berikutnya.');
    }
}
