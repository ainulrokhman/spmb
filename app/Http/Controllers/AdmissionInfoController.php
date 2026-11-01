<?php

namespace App\Http\Controllers;

use App\Models\AdmissionWave;
use App\Models\SchoolUnit;

class AdmissionInfoController extends Controller
{
    /**
     * Public information page: the school units that currently open
     * registration, with their open waves.
     */
    public function index()
    {
        $units = SchoolUnit::query()
            ->with(['program', 'admissionWaves' => function ($query) {
                $query->with('academicYear')->orderBy('opens_at');
            }])
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (SchoolUnit $unit) {
                $unit->setAttribute('open_waves', $unit->admissionWaves
                    ->filter(fn (AdmissionWave $wave) => $wave->status() === AdmissionWave::STATUS_OPEN)
                    ->values());

                return $unit;
            })
            ->filter(fn (SchoolUnit $unit) => $unit->getAttribute('open_waves')->isNotEmpty())
            ->values();

        return view('admissions.info', compact('units'));
    }
}
