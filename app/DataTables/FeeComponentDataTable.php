<?php

namespace App\DataTables;

use App\Models\FeeComponent;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class FeeComponentDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $feeComponents = FeeComponent::select(['id', 'code', 'name', 'description', 'is_active']);

        return DataTables::of($feeComponents)
            ->editColumn('code', $this->codeColumn())
            ->editColumn('is_active', $this->statusColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['code', 'is_active', 'actions'])
            ->make(true);
    }

    /**
     * Build the `code` badge renderer.
     */
    private function codeColumn(): \Closure
    {
        return function ($feeComponent) {
            return '<span class="badge bg-dark bg-opacity-10 text-dark fw-bold py-2 px-3 fs-7 border border-dark border-opacity-10 rounded-pill">'.e($feeComponent->code).'</span>';
        };
    }

    /**
     * Build the `is_active` status badge renderer.
     */
    private function statusColumn(): \Closure
    {
        return function ($feeComponent) {
            if ($feeComponent->is_active) {
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
        return function ($feeComponent) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('fee-component-edit')) {
                $html .= '<a href="'.route('admin.fee-components.edit', $feeComponent->id).'" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Edit Fee Component"><i class="bi bi-pencil-square text-primary"></i> Ubah</a>';
            }
            if (auth()->user()->can('fee-component-delete')) {
                $html .= '<form action="'.route('admin.fee-components.destroy', $feeComponent->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Anda yakin ingin menghapus komponen biaya ini? Pemetaan unit dan nominalnya juga akan terhapus.\')">';
                $html .= csrf_field().method_field('DELETE');
                $html .= '<button type="submit" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Delete Fee Component"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
