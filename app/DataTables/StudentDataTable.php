<?php

namespace App\DataTables;

use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class StudentDataTable
{
    /**
     * Build the DataTable response for the student roster:
     * applicants who completed re-registration. Committee
     * members with unit assignments are restricted to their
     * units by the controller through the `unit_scope` value.
     */
    public function make(): JsonResponse
    {
        $students = Application::query()
            ->with(['user', 'schoolUnit', 'admissionWave.academicYear', 'admissionTrack', 'major', 'invoice'])
            ->where('status', 're_registered')
            ->when(request()->filled('unit_scope'), function ($query) {
                $query->whereIn('school_unit_id', explode(',', (string) request()->input('unit_scope')));
            })
            ->when(request()->filled('school_unit_id'), fn ($query) => $query->where('school_unit_id', request()->integer('school_unit_id')))
            ->when(request()->filled('admission_wave_id'), fn ($query) => $query->where('admission_wave_id', request()->integer('admission_wave_id')))
            ->when(request()->filled('admission_track_id'), fn ($query) => $query->where('admission_track_id', request()->integer('admission_track_id')))
            ->orderBy('school_unit_id')
            ->orderBy('child_name');

        return DataTables::of($students)
            ->addColumn('student', $this->studentColumn())
            ->addColumn('unit', $this->unitColumn())
            ->addColumn('major_name', fn ($student) => e($student->major?->name ?? '—'))
            ->addColumn('gender_label', fn ($student) => $student->gender === 'L' ? 'Laki-laki' : 'Perempuan')
            ->addColumn('guardian', $this->guardianColumn())
            ->addColumn('paid_at', fn ($student) => $student->invoice?->paid_at?->format('d/m/Y H:i') ?? '—')
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['student', 'unit', 'guardian', 'actions'])
            ->make(true);
    }

    private function studentColumn(): \Closure
    {
        return function ($student) {
            return '<span class="fw-semibold text-body">'.e($student->child_name ?? '-').'</span>'
                .'<span class="text-muted small d-block">No. '.e($student->registration_number ?? '-').'</span>';
        };
    }

    private function unitColumn(): \Closure
    {
        return function ($student) {
            return '<span class="text-body">'.e($student->schoolUnit?->name ?? '-').'</span>'
                .'<span class="text-muted small d-block">'.e($student->admissionWave?->name ?? '').' ('.e($student->admissionWave?->academicYear?->name ?? '').') &middot; '.e($student->admissionTrack?->name ?? '').'</span>';
        };
    }

    private function guardianColumn(): \Closure
    {
        return function ($student) {
            return '<span class="text-body">'.e($student->user?->name ?? '-').'</span>'
                .'<span class="text-muted small d-block">'.e($student->user?->phone ?? '-').'</span>';
        };
    }

    private function actionsColumn(): \Closure
    {
        return function ($student) {
            return '<a href="'.route('admin.applications.show', $student).'" class="btn btn-sm btn-light border" title="Detail Pendaftar"><i class="bi bi-eye text-primary"></i></a>';
        };
    }
}
