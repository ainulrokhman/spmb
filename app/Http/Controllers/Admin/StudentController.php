<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ReportServiceInterface;
use App\DataTables\StudentDataTable;
use App\Http\Controllers\Admin\Concerns\ScopesUnits;
use App\Http\Controllers\Controller;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Daftar Siswa: the roster of applicants in every status,
 * filterable by unit / wave / track / status, scoped to the
 * committee member's assigned units, with a CSV export that
 * follows the active filters.
 */
class StudentController extends Controller
{
    use ScopesUnits;

    public function __construct(
        private readonly ReportServiceInterface $reports,
    ) {}

    public function index(Request $request, StudentDataTable $dataTable)
    {
        if ($request->ajax()) {
            if (! $request->user()->hasGlobalUnitAccess()) {
                $request->merge(['unit_scope' => implode(',', $request->user()->accessibleUnitIds())]);
            }

            return $dataTable->make();
        }

        $units = $this->accessibleUnits();
        $waves = AdmissionWave::query()
            ->whereIn('school_unit_id', $units->pluck('id'))
            ->with(['academicYear', 'schoolUnit'])
            ->orderBy('opens_at')
            ->get();
        $tracks = AdmissionTrack::query()
            ->whereIn('school_unit_id', $units->pluck('id'))
            ->orderBy('name')
            ->get();
        $statuses = \App\Models\Application::STATUSES;

        return view('admin.students.index', compact('units', 'waves', 'tracks', 'statuses'));
    }

    /**
     * CSV export of the roster, honoring the page filters.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $unitIds = $user->hasGlobalUnitAccess() ? null : $user->accessibleUnitIds();

        $students = $this->reports->applicationsForExport(
            null,
            $request->query('school_unit_id') ? [(int) $request->query('school_unit_id')] : $unitIds,
            $request->query('admission_wave_id') ? (int) $request->query('admission_wave_id') : null,
            $request->query('admission_track_id') ? (int) $request->query('admission_track_id') : null,
            $request->query('status') ? (string) $request->query('status') : null,
        );

        return response()->streamDownload(function () use ($students) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nomor Pendaftaran', 'Nama Anak', 'Status', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Unit', 'Gelombang', 'Jalur', 'Jurusan', 'Nama Wali', 'No HP Wali', 'Tanggal Lunas Tagihan']);

            foreach ($students as $student) {
                fputcsv($out, [
                    $student->registration_number,
                    $student->child_name,
                    $student->statusLabel(),
                    $student->gender === 'L' ? 'Laki-laki' : 'Perempuan',
                    $student->birth_place,
                    $student->birth_date?->format('Y-m-d'),
                    $student->schoolUnit?->name,
                    $student->admissionWave?->name,
                    $student->admissionTrack?->name,
                    $student->major?->name,
                    $student->user?->name,
                    $student->user?->phone,
                    $student->invoice?->paid_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, 'siswa-spmb.csv', ['Content-Type' => 'text/csv']);
    }
}
