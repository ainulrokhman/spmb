<?php

namespace App\DataTables;

use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class DocumentTypeDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $documentTypes = DocumentType::select(['id', 'name', 'code', 'allowed_extensions', 'max_size_kb', 'is_active']);

        return DataTables::of($documentTypes)
            ->editColumn('code', $this->codeColumn())
            ->editColumn('max_size_kb', $this->maxSizeColumn())
            ->editColumn('is_active', $this->statusColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['code', 'max_size_kb', 'is_active', 'actions'])
            ->make(true);
    }

    /**
     * Build the `code` column renderer.
     */
    private function codeColumn(): \Closure
    {
        return function ($documentType) {
            return '<span class="badge bg-dark">'.e($documentType->code).'</span>';
        };
    }

    /**
     * Build the `max_size_kb` column renderer.
     */
    private function maxSizeColumn(): \Closure
    {
        return function ($documentType) {
            return number_format($documentType->max_size_kb).' KB';
        };
    }

    /**
     * Build the `is_active` status badge renderer.
     */
    private function statusColumn(): \Closure
    {
        return function ($documentType) {
            if ($documentType->is_active) {
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
        return function ($documentType) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('document-type-edit')) {
                $html .= '<a href="'.route('admin.document-types.edit', $documentType->id).'" class="btn btn-sm btn-light border" title="Ubah Document Type"><i class="bi bi-pencil-square text-primary"></i> Ubah</a>';
            }
            if (auth()->user()->can('document-type-delete')) {
                $html .= '<form action="'.route('admin.document-types.destroy', $documentType->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Anda yakin ingin menghapus jenis berkas ini? Syarat berkas unit yang menggunakannya juga akan terhapus.\')">';
                $html .= csrf_field().method_field('DELETE');
                $html .= '<button type="submit" class="btn btn-sm btn-light border" title="Hapus Document Type"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
