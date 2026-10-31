<?php

namespace App\DataTables;

use App\Models\SchoolUnit;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class SchoolUnitDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $schoolUnits = SchoolUnit::select(['id', 'name', 'code', 'level', 'program', 'is_active']);

        return DataTables::of($schoolUnits)
            ->addColumn('unit_name', $this->unitNameColumn())
            ->editColumn('code', $this->codeColumn())
            ->editColumn('program', $this->programColumn())
            ->editColumn('is_active', $this->statusColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['unit_name', 'code', 'program', 'is_active', 'actions'])
            ->make(true);
    }

    /**
     * Build the `unit_name` column renderer (name with level subtext).
     */
    private function unitNameColumn(): \Closure
    {
        return function ($schoolUnit) {
            return '<div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-building fs-5"></i>
                    </div>
                    <div>
                        <span class="fw-bold text-body d-block">'.e($schoolUnit->name).'</span>
                        <span class="text-muted small">'.e($schoolUnit->level).'</span>
                    </div>
                </div>';
        };
    }

    /**
     * Build the `code` column renderer.
     */
    private function codeColumn(): \Closure
    {
        return function ($schoolUnit) {
            return '<span class="badge bg-dark bg-opacity-10 text-dark fw-bold py-2 px-3 fs-7 border border-dark border-opacity-10 rounded-pill">'.e($schoolUnit->code).'</span>';
        };
    }

    /**
     * Build the `program` column renderer.
     */
    private function programColumn(): \Closure
    {
        return function ($schoolUnit) {
            $color = $schoolUnit->program === 'Boarding' ? 'warning' : 'info';

            return '<span class="badge bg-'.$color.' bg-opacity-10 text-'.$color.' fw-medium py-2 px-3 fs-7 border border-'.$color.' border-opacity-20 rounded-pill">'.e($schoolUnit->program).'</span>';
        };
    }

    /**
     * Build the `is_active` status badge renderer.
     */
    private function statusColumn(): \Closure
    {
        return function ($schoolUnit) {
            if ($schoolUnit->is_active) {
                return '<span class="badge bg-success bg-opacity-10 text-success fw-medium py-2 px-3 fs-7 border border-success border-opacity-20 rounded-pill">Active</span>';
            }

            return '<span class="badge bg-secondary bg-opacity-10 text-secondary fw-medium py-2 px-3 fs-7 border border-secondary border-opacity-20 rounded-pill">Inactive</span>';
        };
    }

    /**
     * Build the `actions` column renderer.
     */
    private function actionsColumn(): \Closure
    {
        return function ($schoolUnit) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('school-unit-edit')) {
                $html .= '<a href="'.route('admin.school-units.document-requirements.edit', $schoolUnit->id).'" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Document Requirements"><i class="bi bi-folder2-open text-warning"></i> Berkas</a>';
                $html .= '<a href="'.route('admin.school-units.fees.edit', $schoolUnit->id).'" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Fee Mappings"><i class="bi bi-cash-stack text-success"></i> Biaya</a>';
                $html .= '<a href="'.route('admin.school-units.edit', $schoolUnit->id).'" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Edit School Unit"><i class="bi bi-pencil-square text-primary"></i> Edit</a>';
            }
            if (auth()->user()->can('school-unit-delete')) {
                $html .= '<form action="'.route('admin.school-units.destroy', $schoolUnit->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to delete this school unit? Its admission tracks and majors will also be deleted.\')">';
                $html .= csrf_field().method_field('DELETE');
                $html .= '<button type="submit" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Delete School Unit"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
