<?php

namespace App\DataTables;

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
        $majors = Major::with('schoolUnit')
            ->select(['id', 'school_unit_id', 'name', 'code', 'is_active'])
            ->when(request()->filled('school_unit_id'), function ($query) {
                $query->where('school_unit_id', request()->integer('school_unit_id'));
            });

        return DataTables::of($majors)
            ->addColumn('unit_name', $this->unitNameColumn())
            ->editColumn('is_active', $this->statusColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['unit_name', 'is_active', 'actions'])
            ->make(true);
    }

    /**
     * Build the `unit_name` column renderer.
     */
    private function unitNameColumn(): \Closure
    {
        return function ($major) {
            return '<span class="fw-semibold text-body">'.e($major->schoolUnit?->name ?? '-').'</span>'
                .'<span class="text-muted small d-block">'.e($major->schoolUnit?->level ?? '').' &middot; '.e($major->schoolUnit?->program ?? '').'</span>';
        };
    }

    /**
     * Build the `is_active` status badge renderer.
     */
    private function statusColumn(): \Closure
    {
        return function ($major) {
            if ($major->is_active) {
                return '<span class="badge bg-success bg-opacity-10 text-success fw-medium py-2 px-3 fs-7 border border-success border-opacity-20 rounded-pill">Aktif</span>';
            }

            return '<span class="badge bg-secondary bg-opacity-10 text-secondary fw-medium py-2 px-3 fs-7 border border-secondary border-opacity-20 rounded-pill">Nonaktif</span>';
        };
    }

    /**
     * Build the `actions` column renderer.
     */
    private function actionsColumn(): \Closure
    {
        return function ($major) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('major-edit')) {
                $html .= '<a href="'.route('admin.majors.edit', $major->id).'" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Edit Major"><i class="bi bi-pencil-square text-primary"></i> Ubah</a>';
            }
            if (auth()->user()->can('major-delete')) {
                $html .= '<form action="'.route('admin.majors.destroy', $major->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Anda yakin ingin menghapus jurusan ini?\')">';
                $html .= csrf_field().method_field('DELETE');
                $html .= '<button type="submit" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Delete Major"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
