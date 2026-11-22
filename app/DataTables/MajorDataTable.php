<?php

namespace App\DataTables;

use App\Models\AcademicYear;
use App\Models\Major;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class MajorDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $activeYearId = AcademicYear::query()->where('is_active', true)->value('id');

        $majors = Major::with('schoolUnit.program')
            ->select(['id', 'school_unit_id', 'name', 'code', 'is_active', 'quota', 'quota_putra', 'quota_putri'])
            ->withCount(['applications as registered_count' => function ($query) use ($activeYearId) {
                $query->whereNotNull('registration_number')
                    ->when($activeYearId !== null, fn ($q) => $q->where('academic_year_id', $activeYearId));
            }])
            ->when(request()->filled('school_unit_id'), function ($query) {
                $query->where('school_unit_id', request()->integer('school_unit_id'));
            })
            ->when(request()->filled('unit_scope'), function ($query) {
                $query->whereIn('school_unit_id', explode(',', (string) request()->input('unit_scope')));
            });

        return DataTables::of($majors)
            ->addColumn('unit_name', $this->unitNameColumn())
            ->addColumn('quota_info', $this->quotaColumn())
            ->editColumn('is_active', $this->statusColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['unit_name', 'quota_info', 'is_active', 'actions'])
            ->make(true);
    }

    /**
     * Build the `quota_info` column renderer: the configured
     * quotas plus how many seats the active year already took.
     */
    private function quotaColumn(): \Closure
    {
        return function ($major) {
            return e($major->quotaLabel())
                .'<div class="text-muted small">Terisi: '.(int) ($major->registered_count ?? 0).'</div>';
        };
    }

    /**
     * Build the `unit_name` column renderer.
     */
    private function unitNameColumn(): \Closure
    {
        return function ($major) {
            return '<span class="fw-semibold text-body">'.e($major->schoolUnit?->name ?? '-').'</span>'
                .'<span class="text-muted small d-block">'.e($major->schoolUnit?->level ?? '').' &middot; '.e($major->schoolUnit?->program?->name ?? '').'</span>';
        };
    }

    /**
     * Build the `is_active` status badge renderer.
     */
    private function statusColumn(): \Closure
    {
        return function ($major) {
            if ($major->is_active) {
                return '<span class="badge bg-success">Aktif</span>';
            }

            return '<span class="badge bg-secondary">Nonaktif</span>';
        };
    }

    /**
     * Build the `actions` column renderer.
     */
    private function actionsColumn(): \Closure
    {
        return function ($major) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            $filterQuery = request()->integer('school_unit_id') > 0 ? '?school_unit_id='.request()->integer('school_unit_id') : '';
            if (auth()->user()->can('major-edit')) {
                $html .= '<a href="'.route('admin.majors.edit', $major->id).$filterQuery.'" class="btn btn-sm btn-light border" title="Ubah Major"><i class="bi bi-pencil-square text-primary"></i> Ubah</a>';
            }
            if (auth()->user()->can('major-delete')) {
                $html .= '<form action="'.route('admin.majors.destroy', $major->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Anda yakin ingin menghapus jurusan ini?\')">';
                $html .= csrf_field().method_field('DELETE');
                $html .= '<input type="hidden" name="return_school_unit_id" value="'.request()->integer('school_unit_id').'">';
                $html .= '<button type="submit" class="btn btn-sm btn-light border" title="Hapus Major"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
