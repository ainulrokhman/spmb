<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ReportServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\SchoolUnit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SPMB reports (Bab 7): per-unit recaps of applicants and
 * re-registration invoices, with CSV exports. Committee members
 * are scoped to their assigned units throughout.
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportServiceInterface $reports,
    ) {}

    /**
     * Report page: filters (year, unit, wave) and the per-unit
     * recap tables.
     */
    public function index(Request $request)
    {
        $unitIds = $this->accessibleUnitIds($request);
        $years = AcademicYear::query()->orderByDesc('name')->get();
        $units = SchoolUnit::query()
            ->when($unitIds !== null, fn ($query) => $query->whereIn('id', $unitIds))
            ->orderBy('name')
            ->get();

        $yearId = $this->selectedYearId($request, $years);
        $unitFilter = $request->query('school_unit_id') ? (int) $request->query('school_unit_id') : null;
        $waveId = $request->query('admission_wave_id') ? (int) $request->query('admission_wave_id') : null;

        $waves = AdmissionWave::query()
            ->whereIn('school_unit_id', $units->pluck('id'))
            ->when($yearId !== null, fn ($query) => $query->where('academic_year_id', $yearId))
            ->with(['schoolUnit', 'academicYear'])
            ->orderBy('opens_at')
            ->get();

        $scope = $unitFilter !== null ? [$unitFilter] : $unitIds;
        $recap = $this->reports->unitRecap($yearId, $scope, $waveId);
        $statuses = Application::STATUSES;

        return view('admin.reports.index', compact('years', 'units', 'waves', 'yearId', 'unitFilter', 'waveId', 'recap', 'statuses'));
    }

    /**
     * CSV export of applicants matching the filters.
     */
    public function exportApplications(Request $request): StreamedResponse
    {
        $unitIds = $this->accessibleUnitIds($request);
        $yearId = $request->query('academic_year_id') ? (int) $request->query('academic_year_id') : null;

        $applications = $this->reports->applicationsForExport(
            $yearId,
            $request->query('school_unit_id') ? [(int) $request->query('school_unit_id')] : $unitIds,
            $request->query('admission_wave_id') ? (int) $request->query('admission_wave_id') : null,
            $request->query('admission_track_id') ? (int) $request->query('admission_track_id') : null,
            $request->query('status') ?: null,
        );

        return response()->streamDownload(function () use ($applications) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nomor Pendaftaran', 'Nama Anak', 'Jenis Kelamin', 'Tempat Lahir', 'Tanggal Lahir', 'Unit', 'Gelombang', 'Jalur', 'Jurusan', 'Nama Wali', 'No HP Wali', 'Status', 'Biaya Formulir', 'Status Biaya Formulir', 'Tanggal Kirim']);

            foreach ($applications as $application) {
                fputcsv($out, [
                    $application->registration_number,
                    $application->child_name,
                    $application->gender === 'L' ? 'Laki-laki' : 'Perempuan',
                    $application->birth_place,
                    $application->birth_date?->format('Y-m-d'),
                    $application->schoolUnit?->name,
                    $application->admissionWave?->name,
                    $application->admissionTrack?->name,
                    $application->major?->name,
                    $application->user?->name,
                    $application->user?->phone,
                    $application->statusLabel(),
                    $application->form_fee_amount,
                    $application->feeStatusLabel(),
                    $application->submitted_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, 'pendaftar-spmb.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * CSV export of re-registration invoices matching the filters.
     */
    public function exportInvoices(Request $request): StreamedResponse
    {
        $unitIds = $this->accessibleUnitIds($request);
        $yearId = $request->query('academic_year_id') ? (int) $request->query('academic_year_id') : null;

        $invoices = $this->reports->invoicesForExport(
            $yearId,
            $request->query('school_unit_id') ? [(int) $request->query('school_unit_id')] : $unitIds,
            $request->query('admission_wave_id') ? (int) $request->query('admission_wave_id') : null,
            $request->query('status') ?: null,
        );

        return response()->streamDownload(function () use ($invoices) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Nomor Pendaftaran', 'Nama Anak', 'Unit', 'Gelombang', 'Total Tagihan', 'Diskon', 'Harus Dibayar', 'Status Tagihan', 'Tanggal Lunas']);

            foreach ($invoices as $invoice) {
                fputcsv($out, [
                    $invoice->application?->registration_number,
                    $invoice->application?->child_name,
                    $invoice->application?->schoolUnit?->name,
                    $invoice->application?->admissionWave?->name,
                    $invoice->total,
                    $invoice->discountAmount(),
                    $invoice->payableAmount(),
                    $invoice->statusLabel(),
                    $invoice->paid_at?->format('Y-m-d H:i'),
                ]);
            }

            fclose($out);
        }, 'tagihan-spmb.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * The academic year the report shows: the requested one, else
     * the active year.
     */
    private function selectedYearId(Request $request, $years): ?int
    {
        if ($request->query('academic_year_id')) {
            return (int) $request->query('academic_year_id');
        }

        return $years->firstWhere('is_active', true)?->id ?? $years->first()?->id;
    }

    /**
     * Unit ids the current user may work with, or null for global
     * (foundation-level) access.
     *
     * @return list<int>|null
     */
    private function accessibleUnitIds(Request $request): ?array
    {
        $user = $request->user();

        return $user->hasGlobalUnitAccess() ? null : $user->accessibleUnitIds();
    }
}
