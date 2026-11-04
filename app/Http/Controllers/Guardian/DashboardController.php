<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\GuardianProgressService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * The guardian's dashboard: one card per child showing the
     * journey step by step — what is done, what is theirs to do
     * next and what is waiting on the committee — plus one
     * action list on top collecting everything that needs the
     * guardian across all their children.
     */
    public function index(Request $request, GuardianProgressService $progress): View
    {
        $applications = $request->user()->visibleApplications()
            ->with(['schoolUnit.program', 'admissionWave.academicYear', 'admissionTrack', 'invoice.installments'])
            ->latest()
            ->get();

        $steps = $applications->mapWithKeys(
            fn (Application $application) => [$application->id => $progress->stepsFor($application)],
        );
        $actions = $progress->actionsFor($applications);

        return view('guardian.dashboard', compact('applications', 'steps', 'actions'));
    }
}
