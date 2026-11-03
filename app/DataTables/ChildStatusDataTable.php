<?php

namespace App\DataTables;

use App\Models\ChildStatus;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class ChildStatusDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $statuses = ChildStatus::select(['id', 'name', 'description', 'is_active']);

        return DataTables::of($statuses)
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
        return function ($childStatus) {
            if ($childStatus->is_active) {
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
        return function ($childStatus) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('child-status-edit')) {
                $html .= '<a href="'.route('admin.child-statuses.edit', $childStatus->id).'" class="btn btn-sm btn-light border" title="Ubah ChildStatus"><i class="bi bi-pencil-square text-primary"></i> Ubah</a>';
            }
            if (auth()->user()->can('child-status-delete')) {
                $html .= '<form action="'.route('admin.child-statuses.destroy', $childStatus->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Anda yakin ingin menghapus program ini?\')">';
                $html .= csrf_field().method_field('DELETE');
                $html .= '<button type="submit" class="btn btn-sm btn-light border" title="Hapus ChildStatus"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
