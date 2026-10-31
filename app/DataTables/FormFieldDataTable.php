<?php

namespace App\DataTables;

use App\Models\FormField;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class FormFieldDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $formFields = FormField::with('schoolUnit')
            ->select(['id', 'school_unit_id', 'key', 'label', 'type', 'is_required', 'section', 'sort_order', 'is_active'])
            ->when(request()->filled('school_unit_id'), function ($query) {
                $query->where('school_unit_id', request()->integer('school_unit_id'));
            })
            ->orderBy('school_unit_id')
            ->orderBy('sort_order');

        return DataTables::of($formFields)
            ->addColumn('unit_name', $this->unitNameColumn())
            ->addColumn('field_label', $this->fieldLabelColumn())
            ->editColumn('type', $this->typeColumn())
            ->editColumn('is_required', $this->requiredColumn())
            ->editColumn('is_active', $this->statusColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['unit_name', 'field_label', 'type', 'is_required', 'is_active', 'actions'])
            ->make(true);
    }

    /**
     * Build the `unit_name` column renderer.
     */
    private function unitNameColumn(): \Closure
    {
        return function ($formField) {
            return '<span class="fw-semibold text-body">'.e($formField->schoolUnit?->name ?? '-').'</span>'
                .'<span class="text-muted small d-block">'.e($formField->schoolUnit?->level ?? '').' &middot; '.e($formField->schoolUnit?->program ?? '').'</span>';
        };
    }

    /**
     * Build the `field_label` column renderer (label with key subtext).
     */
    private function fieldLabelColumn(): \Closure
    {
        return function ($formField) {
            return '<span class="fw-semibold text-body">'.e($formField->label).'</span>'
                .'<span class="text-muted small d-block"><code>'.e($formField->key).'</code></span>';
        };
    }

    /**
     * Build the `type` badge renderer.
     */
    private function typeColumn(): \Closure
    {
        return function ($formField) {
            return '<span class="badge bg-info bg-opacity-10 text-info fw-medium py-2 px-3 fs-7 border border-info border-opacity-20 rounded-pill">'.e($formField->type).'</span>';
        };
    }

    /**
     * Build the `is_required` badge renderer.
     */
    private function requiredColumn(): \Closure
    {
        return function ($formField) {
            if ($formField->is_required) {
                return '<span class="badge bg-danger bg-opacity-10 text-danger fw-medium py-2 px-3 fs-7 border border-danger border-opacity-20 rounded-pill">Required</span>';
            }

            return '<span class="badge bg-secondary bg-opacity-10 text-secondary fw-medium py-2 px-3 fs-7 border border-secondary border-opacity-20 rounded-pill">Optional</span>';
        };
    }

    /**
     * Build the `is_active` status badge renderer.
     */
    private function statusColumn(): \Closure
    {
        return function ($formField) {
            if ($formField->is_active) {
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
        return function ($formField) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('form-field-edit')) {
                $html .= '<a href="'.route('admin.form-fields.edit', $formField->id).'" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Edit Form Field"><i class="bi bi-pencil-square text-primary"></i> Edit</a>';
            }
            if (auth()->user()->can('form-field-delete')) {
                $html .= '<form action="'.route('admin.form-fields.destroy', $formField->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to delete this form field?\')">';
                $html .= csrf_field().method_field('DELETE');
                $html .= '<button type="submit" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Delete Form Field"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
