<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface ReportServiceInterface
{
    /**
     * Dashboard summary for the active academic year: applicant
     * counts per status and per-unit reception totals. $unitIds
     * scopes the numbers to a committee member's units (null = all).
     *
     * @param  list<int>|null  $unitIds
     * @return array<string, mixed>
     */
    public function dashboardSummary(?array $unitIds): array;

    /**
     * Per-unit recap for the report page: applicants per wave,
     * track and status, the form-fee payment recap, plus the
     * re-registration / invoice recap.
     *
     * @param  list<int>|null  $unitIds
     * @return list<array<string, mixed>>
     */
    public function unitRecap(?int $academicYearId, ?array $unitIds, ?int $waveId = null): array;

    /**
     * Applications matching the report filters, for CSV export.
     *
     * @param  list<int>|null  $unitIds
     */
    public function applicationsForExport(?int $academicYearId, ?array $unitIds, ?int $waveId = null, ?int $trackId = null, ?string $status = null): Collection;

    /**
     * Invoices matching the report filters, for CSV export.
     *
     * @param  list<int>|null  $unitIds
     */
    public function invoicesForExport(?int $academicYearId, ?array $unitIds, ?int $waveId = null, ?string $status = null): Collection;

    /**
     * Applications matching the report filters with their
     * settled form-fee payment loaded, for the form-payments
     * CSV export.
     *
     * @param  list<int>|null  $unitIds
     */
    public function formPaymentsForExport(?int $academicYearId, ?array $unitIds, ?int $waveId = null): Collection;
}
