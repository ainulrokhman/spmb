<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\SelectionRuleServiceInterface;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;
use App\Http\Controllers\Admin\Concerns\ScopesUnits;
use App\Http\Controllers\Controller;
use App\Models\ChildStatus;
use App\Models\SchoolUnit;
use App\Models\SelectionRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SelectionRuleController extends Controller
{
    use HandlesServiceExceptions, ScopesUnits;

    public function __construct(
        protected SelectionRuleServiceInterface $rules
    ) {}

    /**
     * The unit's selection page: the opt-in toggle plus its
     * point rules (committee members see only their units).
     */
    public function index(SchoolUnit $schoolUnit)
    {
        Gate::authorize('selection-manage');
        $this->ensureUnitAccess($schoolUnit->id);

        $rules = $schoolUnit->selectionRules()->orderBy('sort_order')->orderBy('id')->get();

        return view('admin.selection.index', compact('schoolUnit', 'rules'));
    }

    /**
     * Switch points-based selection on or off for the unit.
     */
    public function toggle(SchoolUnit $schoolUnit)
    {
        Gate::authorize('selection-manage');
        $this->ensureUnitAccess($schoolUnit->id);

        $schoolUnit->update(['selection_enabled' => ! $schoolUnit->selection_enabled]);

        return back()->with('success', $schoolUnit->selection_enabled
            ? 'Seleksi poin diaktifkan untuk unit ini.'
            : 'Seleksi poin dinonaktifkan untuk unit ini.');
    }

    /**
     * Show the form for creating a new rule.
     */
    public function create(SchoolUnit $schoolUnit)
    {
        Gate::authorize('selection-manage');
        $this->ensureUnitAccess($schoolUnit->id);

        return view('admin.selection.create', array_merge(
            compact('schoolUnit'),
            $this->formData($schoolUnit)
        ));
    }

    /**
     * Store a newly created rule.
     */
    public function store(Request $request, SchoolUnit $schoolUnit)
    {
        Gate::authorize('selection-manage');
        $this->ensureUnitAccess($schoolUnit->id);

        $data = $this->validateRule($request);

        return $this->runRuleAction(
            fn () => $this->rules->createRule($schoolUnit, $data),
            $schoolUnit,
            'Aturan seleksi ditambahkan.'
        );
    }

    /**
     * Show the form for editing a rule.
     */
    public function edit(SchoolUnit $schoolUnit, SelectionRule $rule)
    {
        Gate::authorize('selection-manage');
        $this->ensureUnitAccess($schoolUnit->id);
        abort_unless($rule->school_unit_id === $schoolUnit->id, 404);

        return view('admin.selection.edit', array_merge(
            compact('schoolUnit', 'rule'),
            $this->formData($schoolUnit)
        ));
    }

    /**
     * Update the specified rule.
     */
    public function update(Request $request, SchoolUnit $schoolUnit, SelectionRule $rule)
    {
        Gate::authorize('selection-manage');
        $this->ensureUnitAccess($schoolUnit->id);
        abort_unless($rule->school_unit_id === $schoolUnit->id, 404);

        $data = $this->validateRule($request);

        return $this->runRuleAction(
            fn () => $this->rules->updateRule($rule, $data),
            $schoolUnit,
            'Aturan seleksi diperbarui.'
        );
    }

    /**
     * Remove the specified rule.
     */
    public function destroy(SchoolUnit $schoolUnit, SelectionRule $rule)
    {
        Gate::authorize('selection-manage');
        $this->ensureUnitAccess($schoolUnit->id);
        abort_unless($rule->school_unit_id === $schoolUnit->id, 404);

        return $this->runRuleAction(
            fn () => $this->rules->deleteRule($rule),
            $schoolUnit,
            'Aturan seleksi dihapus.'
        );
    }

    /**
     * Run a rule service call: a rejected config returns to
     * the form with its input and the error (the shared
     * HandlesServiceExceptions redirect cannot be used here —
     * this index route needs the unit parameter), success
     * goes to the unit's selection page.
     */
    private function runRuleAction(callable $action, SchoolUnit $schoolUnit, string $successMessage)
    {
        try {
            $action();
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()
            ->route('admin.school-units.selection.index', $schoolUnit)
            ->with('success', $successMessage);
    }

    /**
     * Scalar rule fields shared by store and update; the
     * kind-specific settings are validated by the service
     * while it builds the config.
     */
    private function validateRule(Request $request): array
    {
        return $request->validate([
            'kind' => ['required', Rule::in(array_keys(SelectionRule::KIND_LABELS))],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'field_key' => ['nullable', 'string', 'max:255'],
            'points' => ['nullable', 'integer'],
            'points_internal' => ['nullable', 'integer'],
            'points_external' => ['nullable', 'integer'],
            'cap' => ['nullable', 'integer', 'min:0'],
            'max_points' => ['nullable', 'integer', 'min:0'],
            'brackets_text' => ['nullable', 'string'],
            'ranges_text' => ['nullable', 'string'],
            'map_text' => ['nullable', 'string'],
            'status_points' => ['nullable', 'array'],
            'status_points.*' => ['nullable', 'integer'],
        ], [], [
            'kind' => 'jenis aturan',
            'name' => 'nama aturan',
        ]);
    }

    /**
     * Form reference data: the unit's form fields grouped by
     * the types rules can read, plus all child statuses.
     */
    private function formData(SchoolUnit $schoolUnit): array
    {
        $fields = $schoolUnit->formFields()->where('is_active', true)->orderBy('sort_order')->get();

        return [
            'choiceFields' => $fields->whereIn('type', ['select', 'radio', 'checkbox'])->values(),
            'numberFields' => $fields->where('type', 'number')->values(),
            'originFields' => $fields->where('type', 'school_unit')->values(),
            'siblingFields' => $fields->where('type', 'siblings')->values(),
            'statuses' => ChildStatus::orderBy('id')->get(),
        ];
    }
}
