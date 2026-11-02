<?php

namespace App\DataTables;

use App\Models\AdmissionTrack;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class AdmissionTrackDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $admissionTracks = AdmissionTrack::with('schoolUnit.program')
            ->select(['id', 'school_unit_id', 'name', 'is_active'])
            ->when(request()->filled('school_unit_id'), function ($query) {
                $query->where('school_unit_id', request()->integer('school_unit_id'));
            })
            ->when(request()->filled('unit_scope'), function ($query) {
                $query->whereIn('school_unit_id', explode(',', (string) request()->input('unit_scope')));
            });

        return DataTables::of($admissionTracks)
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
        return function ($admissionTrack) {
            return '<span class="fw-semibold text-body">'.e($admissionTrack->schoolUnit?->name ?? '-').'</span>'
                .'<span class="text-muted small d-block">'.e($admissionTrack->schoolUnit?->level ?? '').' &middot; '.e($admissionTrack->schoolUnit?->program?->name ?? '').'</span>';
        };
    }

    /**
     * Build the `is_active` status badge renderer.
     */
    private function statusColumn(): \Closure
    {
        return function ($admissionTrack) {
            if ($admissionTrack->is_active) {
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
        return function ($admissionTrack) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('admission-track-edit')) {
                $html .= '<a href="'.route('admin.admission-tracks.edit', $admissionTrack->id).'" class="btn btn-sm btn-light border" title="Ubah Admission Track"><i class="bi bi-pencil-square text-primary"></i> Ubah</a>';
            }
            if (auth()->user()->can('admission-track-delete')) {
                $html .= '<form action="'.route('admin.admission-tracks.destroy', $admissionTrack->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Anda yakin ingin menghapus jalur pendaftaran ini?\')">';
                $html .= csrf_field().method_field('DELETE');
                $html .= '<button type="submit" class="btn btn-sm btn-light border" title="Hapus Admission Track"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
