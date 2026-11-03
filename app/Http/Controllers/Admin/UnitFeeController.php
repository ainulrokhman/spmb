<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\FeeServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Fee\SyncUnitFeeCardRequest;
use App\Models\ChildStatus;
use App\Models\Fee;
use App\Models\FeeComponent;
use App\Models\SchoolUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class UnitFeeController extends Controller
{
    protected FeeServiceInterface $feeService;

    /**
     * UnitFeeController constructor.
     */
    public function __construct(FeeServiceInterface $feeService)
    {
        $this->feeService = $feeService;
    }

    /**
     * Overview of fee settings per unit ("Biaya per Unit" menu):
     * how many components are active per unit and how many of
     * them still miss their amounts.
     */
    public function index()
    {
        abort_unless(auth()->user()->can('school-unit-edit') || auth()->user()->can('unit-fee-manage'), 403);

        $units = SchoolUnit::query()
            ->with('program')
            ->when(! auth()->user()->hasGlobalUnitAccess(), fn ($query) => $query->whereIn('id', auth()->user()->accessibleUnitIds()))
            ->orderBy('name')
            ->get()
            ->map(function (SchoolUnit $unit) {
                $activeMappings = $unit->fees()->withCount('amounts')->get()
                    ->where('is_active', true);

                return [
                    'unit' => $unit,
                    'active' => $activeMappings->count(),
                    'warnings' => $activeMappings->filter(fn ($fee) => in_array($fee->mode, ['fixed', 'pilihan'], true) && $fee->amounts_count === 0)->count(),
                ];
            });

        return view('admin.school-units.fees-overview', compact('units'));
    }

    /**
     * Show the fee mapping matrix for a school unit, together
     * with a sample-invoice preview: what one student with the
     * chosen track/gender/status combination would be billed
     * by the active mappings.
     */
    public function edit(Request $request, SchoolUnit $schoolUnit)
    {
        abort_unless(auth()->user()->can('school-unit-edit') || auth()->user()->can('unit-fee-manage'), 403);
        abort_unless(auth()->user()->hasUnitAccess($schoolUnit->id), 403);

        $components = FeeComponent::where('is_active', true)->orderBy('name')->get();
        $mappings = $schoolUnit->fees()
            ->with(['feeComponent', 'amounts' => fn ($query) => $query->orderBy('sort_order'), 'amounts.admissionTrack', 'amounts.childStatus'])
            ->withCount('amounts')
            ->get()
            ->keyBy('fee_component_id');
        $tracks = $schoolUnit->admissionTracks()->where('is_active', true)->orderBy('name')->get();
        $childStatuses = ChildStatus::where('is_active', true)->orderBy('id')->get();

        $previewTrackId = $request->query('preview_track_id') !== null && $request->query('preview_track_id') !== ''
            ? (int) $request->query('preview_track_id')
            : null;
        $previewGender = $request->query('preview_gender', 'L');
        $previewStatusParam = $request->query('preview_child_status_id');
        $previewStatusId = ($previewStatusParam === null || $previewStatusParam === '')
            ? ChildStatus::where('name', 'Reguler')->where('is_active', true)->value('id')
            : (int) $previewStatusParam;

        $previewLines = [];
        $previewFixedTotal = 0;

        foreach ($mappings as $mapping) {
            if (! $mapping->is_active) {
                continue;
            }

            $line = ['mapping' => $mapping, 'amount' => null, 'options' => []];

            if ($mapping->mode === Fee::MODE_FIXED) {
                $line['amount'] = $this->feeService->resolveFixedAmount($mapping, $previewTrackId, $previewGender, $previewStatusId);
                $previewFixedTotal += $line['amount'] ?? 0;
            } elseif ($mapping->mode === Fee::MODE_PILIHAN) {
                $line['options'] = $this->feeService->resolveAmounts($mapping, $previewTrackId, $previewGender, $previewStatusId)
                    ->pluck('amount')
                    ->map(fn ($amount) => (int) $amount)
                    ->unique()
                    ->sort()
                    ->values()
                    ->all();
            }

            $previewLines[] = $line;
        }

        return view('admin.school-units.fees', compact(
            'schoolUnit', 'components', 'mappings', 'tracks', 'childStatuses',
            'previewLines', 'previewFixedTotal', 'previewTrackId', 'previewGender', 'previewStatusId'
        ));
    }

    /**
     * Save one component card of the unit's fee page via AJAX:
     * the mapping (mode / minimum / active switch) together
     * with its amount rows. Other cards are never touched.
     */
    public function updateCard(SyncUnitFeeCardRequest $request, SchoolUnit $schoolUnit, FeeComponent $feeComponent): JsonResponse
    {
        abort_unless(auth()->user()->can('school-unit-edit') || auth()->user()->can('unit-fee-manage'), 403);
        abort_unless(auth()->user()->hasUnitAccess($schoolUnit->id), 403);

        try {
            $fee = $this->feeService->syncUnitFeeCard(
                $schoolUnit,
                $feeComponent,
                $request->mapping(),
                $request->has('sync_amounts') ? $request->amountRows() : null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['amounts' => $exception->getMessage()]);
        }

        return response()->json([
            'message' => $fee === null
                ? 'Komponen "'.$feeComponent->name.'" tidak lagi dipakai di unit ini.'
                : 'Kartu "'.$feeComponent->name.'" berhasil disimpan.',
            'active' => $fee?->is_active ?? false,
            'amounts_count' => $fee?->amounts->count() ?? 0,
        ]);
    }
}
