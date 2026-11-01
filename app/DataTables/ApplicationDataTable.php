<?php

namespace App\DataTables;

use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class ApplicationDataTable
{
    /**
     * Build the DataTable response.
     *
     * Only submitted applications (those carrying a registration
     * number) are verification material. Committee members with unit
     * assignments are restricted to their units by the controller
     * through the `unit_scope` request value it injects.
     */
    public function make(): JsonResponse
    {
        $applications = Application::query()
            ->with(['user', 'schoolUnit', 'admissionWave.academicYear', 'admissionTrack'])
            ->whereNotNull('registration_number')
            ->when(request()->filled('unit_scope'), function ($query) {
                $query->whereIn('school_unit_id', explode(',', (string) request()->input('unit_scope')));
            })
            ->when(request()->filled('school_unit_id'), fn ($query) => $query->where('school_unit_id', request()->integer('school_unit_id')))
            ->when(request()->filled('admission_wave_id'), fn ($query) => $query->where('admission_wave_id', request()->integer('admission_wave_id')))
            ->when(request()->filled('admission_track_id'), fn ($query) => $query->where('admission_track_id', request()->integer('admission_track_id')))
            ->when(request()->filled('status'), fn ($query) => $query->where('status', request()->input('status')))
            ->latest('submitted_at');

        return DataTables::of($applications)
            ->addColumn('applicant', $this->applicantColumn())
            ->addColumn('unit', $this->unitColumn())
            ->addColumn('fee', $this->feeColumn())
            ->addColumn('status_badge', $this->statusColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['applicant', 'unit', 'fee', 'status_badge', 'actions'])
            ->make(true);
    }

    private function applicantColumn(): \Closure
    {
        return function ($application) {
            return '<span class="fw-semibold text-body">'.e($application->child_name ?? '-').'</span>'
                .'<span class="text-muted small d-block">Wali: '.e($application->user?->name ?? '-').' &middot; '.e($application->user?->phone ?? '-').'</span>';
        };
    }

    private function unitColumn(): \Closure
    {
        return function ($application) {
            return '<span class="text-body">'.e($application->schoolUnit?->name ?? '-').'</span>'
                .'<span class="text-muted small d-block">'.e($application->admissionWave?->name ?? '').' ('.e($application->admissionWave?->academicYear?->name ?? '').') &middot; '.e($application->admissionTrack?->name ?? '').'</span>';
        };
    }

    private function feeColumn(): \Closure
    {
        return function ($application) {
            $classes = [
                'paid' => 'bg-success',
                'waived' => 'bg-info',
                'free' => 'bg-secondary',
            ];

            $class = $classes[$application->form_fee_status] ?? 'bg-warning text-dark';

            return '<span class="badge '.$class.'">'.e($application->feeStatusLabel()).'</span>';
        };
    }

    private function statusColumn(): \Closure
    {
        return function ($application) {
            $classes = [
                'submitted' => 'bg-warning text-dark',
                'verified' => 'bg-success',
                'needs_revision' => 'bg-danger',
                'rejected' => 'bg-dark',
                'passed' => 'bg-success',
                'failed' => 'bg-dark',
                're_registered' => 'bg-primary',
            ];

            $class = $classes[$application->status] ?? 'bg-secondary';

            return '<span class="badge '.$class.'">'.e($application->statusLabel()).'</span>';
        };
    }

    private function actionsColumn(): \Closure
    {
        return function ($application) {
            $showUrl = route('admin.applications.show', $application);

            return '<div class="d-flex align-items-center justify-content-end gap-1">'
                .'<a href="'.$showUrl.'" class="btn btn-sm btn-outline-primary border-0" title="Lihat & Verifikasi"><i class="bi bi-clipboard-check fs-5"></i></a>'
                .'</div>';
        };
    }
}
