<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\DocumentRequirementServiceInterface;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\SchoolUnit\UpdateDocumentRequirementsRequest;
use App\Models\DocumentType;
use App\Models\SchoolUnit;
use Illuminate\Support\Facades\Gate;

class DocumentRequirementController extends Controller
{
    use HandlesServiceExceptions;

    protected DocumentRequirementServiceInterface $documentRequirementService;

    /**
     * DocumentRequirementController constructor.
     */
    public function __construct(DocumentRequirementServiceInterface $documentRequirementService)
    {
        $this->documentRequirementService = $documentRequirementService;
    }

    /**
     * Show the per-unit document requirement matrix form.
     */
    public function edit(SchoolUnit $schoolUnit)
    {
        abort_unless(auth()->user()->can('school-unit-edit') || auth()->user()->can('unit-requirement-manage'), 403);
        abort_unless(auth()->user()->hasUnitAccess($schoolUnit->id), 403);

        $documentTypes = DocumentType::where('is_active', true)->orderBy('name')->get();
        $requirementMap = $this->documentRequirementService->requirementMapForUnit($schoolUnit);

        return view('admin.school-units.document-requirements', compact('schoolUnit', 'documentTypes', 'requirementMap'));
    }

    /**
     * Replace the document requirements of the unit.
     */
    public function update(UpdateDocumentRequirementsRequest $request, SchoolUnit $schoolUnit)
    {
        abort_unless(auth()->user()->can('school-unit-edit') || auth()->user()->can('unit-requirement-manage'), 403);
        abort_unless(auth()->user()->hasUnitAccess($schoolUnit->id), 403);

        if ($errorRedirect = $this->runServiceAction(fn () => $this->documentRequirementService->syncUnitRequirements($schoolUnit, $request->levelsToSync()), 'admin.school-units.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.school-units.index')
            ->with('success', 'Syarat berkas untuk "'.$schoolUnit->name.'" berhasil diperbarui.');
    }
}
