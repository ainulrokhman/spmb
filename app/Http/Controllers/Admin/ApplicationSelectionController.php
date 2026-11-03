<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\SelectionServiceInterface;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Admin\Concerns\ScopesUnits;
use App\Http\Controllers\Controller;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\SelectionRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ApplicationSelectionController extends Controller
{
    use HandlesServiceExceptions, ScopesUnits;

    public function __construct(
        protected SelectionServiceInterface $selection
    ) {}

    /**
     * Recompute an application's automatic points (manual
     * entries and the adjustment are preserved).
     */
    public function recalculate(Application $application)
    {
        Gate::authorize('selection-manage');
        $this->ensureUnitAccess($application->school_unit_id);

        $snapshot = $this->selection->calculate($application);

        if ($snapshot === null) {
            return back()->with('error', 'Unit pendaftar ini tidak memakai seleksi poin.');
        }

        return back()->with('success', 'Poin dihitung ulang. Total sekarang '.$snapshot['total'].'.');
    }

    /**
     * Enter the points of one manual criterion.
     */
    public function manual(Request $request, Application $application)
    {
        Gate::authorize('selection-manage');
        $this->ensureUnitAccess($application->school_unit_id);

        $validated = $request->validate([
            'rule_id' => ['required', 'exists:selection_rules,id'],
            'points' => ['required', 'integer'],
        ], [], ['points' => 'poin']);

        $rule = SelectionRule::findOrFail($validated['rule_id']);

        if ($errorRedirect = $this->runServiceAction(fn () => $this->selection->setManualScore($application, $rule, (int) $validated['points'], $request->user()), 'admin.applications.index')) {
            return $errorRedirect;
        }

        return back()->with('success', 'Poin kriteria "'.$rule->name.'" disimpan.');
    }

    /**
     * Set (or clear, with value 0) the committee adjustment.
     */
    public function adjustment(Request $request, Application $application)
    {
        Gate::authorize('selection-manage');
        $this->ensureUnitAccess($application->school_unit_id);

        $validated = $request->validate([
            'value' => ['required', 'integer'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [], ['value' => 'nilai koreksi', 'reason' => 'alasan']);

        if ($errorRedirect = $this->runServiceAction(fn () => $this->selection->setAdjustment($application, (int) $validated['value'], $validated['reason'] ?? null, $request->user()), 'admin.applications.index')) {
            return $errorRedirect;
        }

        return back()->with('success', 'Koreksi poin disimpan.');
    }

    /**
     * Preview page for "Tetapkan dari Poin": the wave's
     * verified applications ranked by score with the decision
     * each would receive.
     */
    public function preview(Request $request)
    {
        Gate::authorize('selection-manage');

        $validated = $request->validate([
            'admission_wave_id' => ['required', Rule::exists('admission_waves', 'id')],
        ]);

        $wave = AdmissionWave::with('schoolUnit')->findOrFail($validated['admission_wave_id']);
        $this->ensureUnitAccess($wave->school_unit_id);

        if (! $wave->schoolUnit->selection_enabled || $wave->passing_score === null) {
            return redirect()->route('admin.applications.index')
                ->with('error', 'Gelombang itu belum dapat ditetapkan dari poin (seleksi unit nonaktif atau batas poin belum diatur).');
        }

        try {
            $rows = $this->selection->previewByPoints($wave);
        } catch (\InvalidArgumentException $e) {
            return redirect()->route('admin.applications.index')->with('error', $e->getMessage());
        }

        return view('admin.selection.preview', compact('wave', 'rows'));
    }

    /**
     * Apply the previewed decisions to the wave.
     */
    public function apply(Request $request)
    {
        Gate::authorize('selection-manage');

        $validated = $request->validate([
            'admission_wave_id' => ['required', Rule::exists('admission_waves', 'id')],
        ]);

        $wave = AdmissionWave::with('schoolUnit')->findOrFail($validated['admission_wave_id']);
        $this->ensureUnitAccess($wave->school_unit_id);

        $counts = null;

        if ($errorRedirect = $this->runServiceAction(function () use ($wave, &$counts) {
            $counts = $this->selection->applyByPoints($wave);
        }, 'admin.applications.index')) {
            return $errorRedirect;
        }

        return redirect()
            ->route('admin.applications.index', ['admission_wave_id' => $wave->id])
            ->with('success', 'Hasil ditetapkan dari poin: '.$counts['passed'].' Lulus, '.$counts['failed'].' Tidak Lulus.');
    }
}
