<?php

namespace App\DataTables;

use App\Models\AdmissionWave;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class AdmissionWaveDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $admissionWaves = AdmissionWave::with(['schoolUnit', 'academicYear'])
            ->withCount('admissionTracks')
            ->select(['id', 'school_unit_id', 'academic_year_id', 'name', 'quota', 'opens_at', 'closes_at', 'is_active'])
            ->when(request()->filled('school_unit_id'), function ($query) {
                $query->where('school_unit_id', request()->integer('school_unit_id'));
            });

        return DataTables::of($admissionWaves)
            ->addColumn('unit_name', $this->unitNameColumn())
            ->addColumn('year_name', $this->yearNameColumn())
            ->editColumn('quota', $this->quotaColumn())
            ->addColumn('period', $this->periodColumn())
            ->addColumn('status', $this->statusColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['unit_name', 'year_name', 'quota', 'period', 'status', 'actions'])
            ->make(true);
    }

    /**
     * Build the `unit_name` column renderer.
     */
    private function unitNameColumn(): \Closure
    {
        return function ($admissionWave) {
            return '<span class="fw-semibold text-body">'.e($admissionWave->schoolUnit?->name ?? '-').'</span>'
                .'<span class="text-muted small d-block">'.e($admissionWave->schoolUnit?->level ?? '').' &middot; '.e($admissionWave->schoolUnit?->program ?? '').'</span>';
        };
    }

    /**
     * Build the `year_name` column renderer.
     */
    private function yearNameColumn(): \Closure
    {
        return function ($admissionWave) {
            return e($admissionWave->academicYear?->name ?? '-');
        };
    }

    /**
     * Build the `quota` column renderer.
     */
    private function quotaColumn(): \Closure
    {
        return function ($admissionWave) {
            if ($admissionWave->quota === null) {
                return '<span class="text-muted">Unlimited</span>';
            }

            return number_format($admissionWave->quota);
        };
    }

    /**
     * Build the `period` column renderer.
     */
    private function periodColumn(): \Closure
    {
        return function ($admissionWave) {
            return '<span class="small d-block">'.e($admissionWave->opens_at->format('d M Y H:i')).'</span>'
                .'<span class="text-muted small">&rarr; '.e($admissionWave->closes_at->format('d M Y H:i')).'</span>';
        };
    }

    /**
     * Build the computed `status` badge renderer.
     */
    private function statusColumn(): \Closure
    {
        return function ($admissionWave) {
            [$label, $color] = match ($admissionWave->status()) {
                AdmissionWave::STATUS_OPEN => ['Dibuka', 'success'],
                AdmissionWave::STATUS_UPCOMING => ['Akan Datang', 'info'],
                AdmissionWave::STATUS_CLOSED => ['Ditutup', 'dark'],
                default => ['Nonaktif', 'secondary'],
            };

            return '<span class=\"badge bg-'.$color.'\">'.$label.'</span>';
        };
    }

    /**
     * Build the `actions` column renderer.
     */
    private function actionsColumn(): \Closure
    {
        return function ($admissionWave) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('admission-wave-edit')) {
                $html .= '<a href="'.route('admin.admission-waves.edit', $admissionWave->id).'" class="btn btn-sm btn-light border" title="Ubah Admission Wave"><i class="bi bi-pencil-square text-primary"></i> Ubah</a>';
            }
            if (auth()->user()->can('admission-wave-delete')) {
                $html .= '<form action="'.route('admin.admission-waves.destroy', $admissionWave->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Anda yakin ingin menghapus gelombang ini?\')">';
                $html .= csrf_field().method_field('DELETE');
                $html .= '<button type="submit" class="btn btn-sm btn-light border" title="Hapus Admission Wave"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
