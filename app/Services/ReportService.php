<?php

namespace App\Services;

use App\Contracts\ReportServiceInterface;
use App\Models\AcademicYear;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SchoolUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportService implements ReportServiceInterface
{
    /**
     * Dashboard summary for the active academic year: applicant
     * counts per status and per-unit reception totals. $unitIds
     * scopes the numbers to a committee member's units (null = all).
     *
     * @param  list<int>|null  $unitIds
     * @return array<string, mixed>
     */
    public function dashboardSummary(?array $unitIds): array
    {
        $year = AcademicYear::query()->where('is_active', true)->first();

        $base = $this->scopedApplications($unitIds, $year?->id);

        $statusCounts = array_fill_keys(array_keys(Application::STATUSES), 0);

        foreach ((clone $base)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status') as $status => $count) {
            $statusCounts[$status] = (int) $count;
        }

        $units = SchoolUnit::query()
            ->when($unitIds !== null, fn ($query) => $query->whereIn('id', $unitIds))
            ->orderBy('name')
            ->get()
            ->map(function (SchoolUnit $unit) use ($year) {
                $formFees = $this->paidAmountForUnit(Application::class, $unit->id, $year?->id);
                $invoices = $this->paidAmountForUnit(Invoice::class, $unit->id, $year?->id);

                return [
                    'unit' => $unit,
                    'applicants' => Application::query()
                        ->where('school_unit_id', $unit->id)
                        ->when($year !== null, fn ($query) => $query->where('academic_year_id', $year->id))
                        ->count(),
                    're_registered' => Application::query()
                        ->where('school_unit_id', $unit->id)
                        ->where('status', 're_registered')
                        ->when($year !== null, fn ($query) => $query->where('academic_year_id', $year->id))
                        ->count(),
                    'form_fees_received' => $formFees,
                    'invoices_received' => $invoices,
                    'total_received' => $formFees + $invoices,
                ];
            })
            ->all();

        $recent = (clone $base)
            ->whereNotNull('registration_number')
            ->with(['schoolUnit', 'admissionWave', 'user'])
            ->latest('submitted_at')
            ->take(5)
            ->get();

        return [
            'academic_year' => $year,
            'status_counts' => $statusCounts,
            'total_applicants' => array_sum($statusCounts),
            'units' => $units,
            'recent_applications' => $recent,
        ];
    }

    /**
     * Per-unit recap for the report page: applicants per wave,
     * track and status, plus the re-registration / invoice recap.
     *
     * @param  list<int>|null  $unitIds
     * @return list<array<string, mixed>>
     */
    public function unitRecap(?int $academicYearId, ?array $unitIds, ?int $waveId = null): array
    {
        $units = SchoolUnit::query()
            ->when($unitIds !== null, fn ($query) => $query->whereIn('id', $unitIds))
            ->orderBy('name')
            ->get();

        $recap = [];

        foreach ($units as $unit) {
            $applications = Application::query()
                ->where('school_unit_id', $unit->id)
                ->when($academicYearId !== null, fn ($query) => $query->where('academic_year_id', $academicYearId))
                ->when($waveId !== null, fn ($query) => $query->where('admission_wave_id', $waveId))
                ->with(['admissionWave', 'admissionTrack'])
                ->get();

            $invoices = Invoice::query()
                ->whereHas('application', function ($query) use ($unit, $academicYearId, $waveId) {
                    $query->where('school_unit_id', $unit->id)
                        ->when($academicYearId !== null, fn ($q) => $q->where('academic_year_id', $academicYearId))
                        ->when($waveId !== null, fn ($q) => $q->where('admission_wave_id', $waveId));
                })
                ->get();

            $recap[] = [
                'unit' => $unit,
                'total_applicants' => $applications->count(),
                'by_wave' => $applications->groupBy(fn (Application $a) => $a->admissionWave?->name ?? '—')
                    ->map->count()->all(),
                'by_track' => $applications->groupBy(fn (Application $a) => $a->admissionTrack?->name ?? '—')
                    ->map->count()->all(),
                'by_status' => $applications->groupBy('status')->map->count()->all(),
                're_registered' => $applications->where('status', 're_registered')->count(),
                'invoices_issued' => $invoices->count(),
                'invoices_paid' => $invoices->where('status', 'paid')->count(),
                'invoices_unpaid' => $invoices->where('status', '!=', 'paid')->count(),
                'billed_total' => (int) $invoices->sum('total'),
                'discount_total' => (int) $invoices->sum(fn (Invoice $invoice) => $invoice->discountAmount()),
                'outstanding_total' => (int) $invoices->where('status', '!=', 'paid')->sum(fn (Invoice $invoice) => $invoice->payableAmount()),
            ];
        }

        return $recap;
    }

    /**
     * Applications matching the report filters, for CSV export.
     *
     * @param  list<int>|null  $unitIds
     */
    public function applicationsForExport(?int $academicYearId, ?array $unitIds, ?int $waveId = null, ?int $trackId = null, ?string $status = null): Collection
    {
        return $this->scopedApplications($unitIds, $academicYearId)
            ->when($waveId !== null, fn ($query) => $query->where('admission_wave_id', $waveId))
            ->when($trackId !== null, fn ($query) => $query->where('admission_track_id', $trackId))
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->with(['schoolUnit', 'admissionWave', 'admissionTrack', 'major', 'user'])
            ->orderBy('school_unit_id')
            ->orderBy('registration_number')
            ->get();
    }

    /**
     * Invoices matching the report filters, for CSV export.
     *
     * @param  list<int>|null  $unitIds
     */
    public function invoicesForExport(?int $academicYearId, ?array $unitIds, ?int $waveId = null, ?string $status = null): Collection
    {
        return Invoice::query()
            ->whereHas('application', function ($query) use ($academicYearId, $unitIds, $waveId) {
                $query->when($unitIds !== null, fn ($q) => $q->whereIn('school_unit_id', $unitIds))
                    ->when($academicYearId !== null, fn ($q) => $q->where('academic_year_id', $academicYearId))
                    ->when($waveId !== null, fn ($q) => $q->where('admission_wave_id', $waveId));
            })
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->with(['application.schoolUnit', 'application.admissionWave', 'payments'])
            ->latest()
            ->get();
    }

    /**
     * Base application query with unit scope and optional year.
     *
     * @param  list<int>|null  $unitIds
     */
    private function scopedApplications(?array $unitIds, ?int $academicYearId): Builder
    {
        return Application::query()
            ->when($unitIds !== null, fn ($query) => $query->whereIn('school_unit_id', $unitIds))
            ->when($academicYearId !== null, fn ($query) => $query->where('academic_year_id', $academicYearId));
    }

    /**
     * Sum of settled payments of one payable type whose application
     * belongs to the given unit (and year, when given).
     */
    private function paidAmountForUnit(string $payableType, int $unitId, ?int $academicYearId): int
    {
        return (int) Payment::query()
            ->where('status', 'paid')
            ->where('payable_type', $payableType)
            ->whereHasMorph('payable', [$payableType], function ($query) use ($payableType, $unitId, $academicYearId) {
                if ($payableType === Application::class) {
                    $query->where('school_unit_id', $unitId)
                        ->when($academicYearId !== null, fn ($q) => $q->where('academic_year_id', $academicYearId));
                } else {
                    $query->whereHas('application', function ($q) use ($unitId, $academicYearId) {
                        $q->where('school_unit_id', $unitId)
                            ->when($academicYearId !== null, fn ($inner) => $inner->where('academic_year_id', $academicYearId));
                    });
                }
            })
            ->sum('amount');
    }
}
