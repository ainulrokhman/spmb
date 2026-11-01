<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ProgramServiceInterface;
use App\DataTables\ProgramDataTable;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Program\StoreProgramRequest;
use App\Http\Requests\Program\UpdateProgramRequest;
use App\Models\Program;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProgramController extends Controller
{
    use HandlesServiceExceptions;

    protected ProgramServiceInterface $programService;

    /**
     * ProgramController constructor.
     */
    public function __construct(ProgramServiceInterface $programService)
    {
        $this->programService = $programService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, ProgramDataTable $programDataTable)
    {
        Gate::authorize('program-list');

        if ($request->ajax()) {
            return $programDataTable->make();
        }

        return view('admin.programs.index');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('program-create');

        return view('admin.programs.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProgramRequest $request)
    {
        Gate::authorize('program-create');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->programService->createProgram($request->validated()), 'admin.programs.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.programs.index')
            ->with('success', 'Program berhasil dibuat.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Program $program)
    {
        Gate::authorize('program-edit');

        return view('admin.programs.edit', compact('program'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProgramRequest $request, Program $program)
    {
        Gate::authorize('program-edit');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->programService->updateProgram($program, $request->validated()), 'admin.programs.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.programs.index')
            ->with('success', 'Program "'.$program->name.'" berhasil diperbarui.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Program $program)
    {
        Gate::authorize('program-delete');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->programService->deleteProgram($program), 'admin.programs.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.programs.index')
            ->with('success', 'Program berhasil dihapus.');
    }
}
