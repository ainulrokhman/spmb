<?php

namespace App\DataTables;

use App\Models\Program;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class ProgramDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $programs = Program::select(['id', 'name', 'description', 'is_active']);

        return DataTables::of($programs)
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
        return function ($program) {
            if ($program->is_active) {
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
        return function ($program) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('program-edit')) {
                $html .= '<a href="'.route('admin.programs.edit', $program->id).'" class="btn btn-sm btn-light border" title="Ubah Program"><i class="bi bi-pencil-square text-primary"></i> Ubah</a>';
            }
            if (auth()->user()->can('program-delete')) {
                $html .= '<form action="'.route('admin.programs.destroy', $program->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Anda yakin ingin menghapus program ini?\')">';
                $html .= csrf_field().method_field('DELETE');
                $html .= '<button type="submit" class="btn btn-sm btn-light border" title="Hapus Program"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
