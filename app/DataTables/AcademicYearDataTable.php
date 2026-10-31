<?php

namespace App\DataTables;

use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class AcademicYearDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $academicYears = AcademicYear::select(['id', 'name', 'is_active']);

        return DataTables::of($academicYears)
            ->editColumn('is_active', $this->statusColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['is_active', 'actions'])
            ->make(true);
    }

    /**
     * Build the `is_active` status badge renderer.
     */
    private function statusColumn(): \Closure
    {
        return function ($academicYear) {
            if ($academicYear->is_active) {
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
        return function ($academicYear) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('academic-year-edit')) {
                $html .= '<a href="'.route('admin.academic-years.edit', $academicYear->id).'" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Edit Academic Year"><i class="bi bi-pencil-square text-primary"></i> Ubah</a>';
            }
            if (auth()->user()->can('academic-year-delete')) {
                $html .= '<form action="'.route('admin.academic-years.destroy', $academicYear->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Anda yakin ingin menghapus tahun ajaran ini?\')">';
                $html .= csrf_field().method_field('DELETE');
                $html .= '<button type="submit" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Delete Academic Year"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
