<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * The guardian's dashboard: their children's applications and
     * the step each one is on.
     */
    public function index(Request $request)
    {
        $applications = $request->user()->applications()
            ->with(['schoolUnit.program', 'admissionWave.academicYear', 'admissionTrack'])
            ->latest()
            ->get();

        return view('guardian.dashboard', compact('applications'));
    }
}
