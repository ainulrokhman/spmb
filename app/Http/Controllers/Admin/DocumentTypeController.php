<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\DocumentTypeServiceInterface;
use App\DataTables\DocumentTypeDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentType\StoreDocumentTypeRequest;
use App\Http\Requests\DocumentType\UpdateDocumentTypeRequest;
use App\Models\DocumentType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DocumentTypeController extends Controller
{
    use HandlesServiceExceptions;

    protected DocumentTypeServiceInterface $documentTypeService;

    /**
     * DocumentTypeController constructor.
     */
    public function __construct(DocumentTypeServiceInterface $documentTypeService)
    {
        $this->documentTypeService = $documentTypeService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, DocumentTypeDataTable $documentTypeDataTable)
    {
        Gate::authorize('document-type-list');

        if ($request->ajax()) {
            return $documentTypeDataTable->make();
        }

        return view('admin.document-types.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('document-type-create');

        return view('admin.document-types.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreDocumentTypeRequest $request)
    {
        Gate::authorize('document-type-create');

        $documentType = $this->documentTypeService->createDocumentType($request->validated());

        return redirect()->route('admin.document-types.index')
            ->with('success', 'Jenis berkas "'.$documentType->name.'" berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(DocumentType $documentType)
    {
        Gate::authorize('document-type-edit');

        return view('admin.document-types.edit', compact('documentType'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateDocumentTypeRequest $request, DocumentType $documentType)
    {
        Gate::authorize('document-type-edit');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->documentTypeService->updateDocumentType($documentType, $request->validated()), 'admin.document-types.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.document-types.index')
            ->with('success', 'Jenis berkas "'.$documentType->name.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DocumentType $documentType)
    {
        Gate::authorize('document-type-delete');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->documentTypeService->deleteDocumentType($documentType), 'admin.document-types.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.document-types.index')
            ->with('success', 'Jenis berkas berhasil dihapus.');
    }
}
