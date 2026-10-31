<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\FormFieldServiceInterface;
use App\DataTables\FormFieldDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\FormField\StoreFormFieldRequest;
use App\Http\Requests\FormField\UpdateFormFieldRequest;
use App\Models\FormField;
use App\Models\SchoolUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FormFieldController extends Controller
{
    use HandlesServiceExceptions;

    protected FormFieldServiceInterface $formFieldService;

    /**
     * FormFieldController constructor.
     */
    public function __construct(FormFieldServiceInterface $formFieldService)
    {
        $this->formFieldService = $formFieldService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, FormFieldDataTable $formFieldDataTable)
    {
        Gate::authorize('form-field-list');

        if ($request->ajax()) {
            return $formFieldDataTable->make();
        }

        $units = SchoolUnit::orderBy('name')->get();

        return view('admin.form-fields.index', compact('units'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('form-field-create');

        $units = SchoolUnit::orderBy('name')->get();

        return view('admin.form-fields.create', compact('units'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFormFieldRequest $request)
    {
        Gate::authorize('form-field-create');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->formFieldService->createFormField($request->validated()), 'admin.form-fields.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.form-fields.index')
            ->with('success', 'Bidang formulir berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(FormField $formField)
    {
        Gate::authorize('form-field-edit');

        $units = SchoolUnit::orderBy('name')->get();

        return view('admin.form-fields.edit', compact('formField', 'units'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFormFieldRequest $request, FormField $formField)
    {
        Gate::authorize('form-field-edit');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->formFieldService->updateFormField($formField, $request->validated()), 'admin.form-fields.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.form-fields.index')
            ->with('success', 'Bidang formulir "'.$formField->label.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FormField $formField)
    {
        Gate::authorize('form-field-delete');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->formFieldService->deleteFormField($formField), 'admin.form-fields.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.form-fields.index')
            ->with('success', 'Form field berhasil dihapus.');
    }

    /**
     * Preview a unit's registration form as applicants will see it.
     */
    public function preview(SchoolUnit $schoolUnit)
    {
        Gate::authorize('form-field-list');

        $fieldGroups = $schoolUnit->formFields()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (FormField $field) => $field->section ?: 'Lainnya');

        return view('admin.form-fields.preview', compact('schoolUnit', 'fieldGroups'));
    }
}
