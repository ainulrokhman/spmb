<?php

namespace App\DataTables;

use App\Models\FeeAmount;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class FeeAmountDataTable
{
    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $feeAmounts = FeeAmount::with(['fee.schoolUnit', 'fee.feeComponent', 'admissionTrack', 'childStatus'])
            ->select(['id', 'fee_id', 'admission_track_id', 'child_status_id', 'gender', 'amount', 'sort_order'])
            ->when(request()->filled('school_unit_id'), function ($query) {
                $query->whereHas('fee', fn ($feeQuery) => $feeQuery->where('school_unit_id', request()->integer('school_unit_id')));
            })
            ->when(request()->filled('unit_scope'), function ($query) {
                $query->whereHas('fee', fn ($feeQuery) => $feeQuery->whereIn('school_unit_id', explode(',', (string) request()->input('unit_scope'))));
            })
            ->when(request()->filled('fee_component_id'), function ($query) {
                $query->whereHas('fee', fn ($feeQuery) => $feeQuery->where('fee_component_id', request()->integer('fee_component_id')));
            })
            ->orderBy('sort_order');

        return DataTables::of($feeAmounts)
            ->addColumn('mapping', $this->mappingColumn())
            ->addColumn('scope', $this->scopeColumn())
            ->editColumn('amount', $this->amountColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['mapping', 'scope', 'amount', 'actions'])
            ->make(true);
    }

    /**
     * Build the `mapping` column renderer (component name first,
     * with the unit and mode as subtext).
     */
    private function mappingColumn(): \Closure
    {
        return function ($feeAmount) {
            return '<span class="fw-semibold text-body">'.e($feeAmount->fee?->feeComponent?->name ?? '-').'</span>'
                .'<span class="text-muted small d-block">'.e($feeAmount->fee?->schoolUnit?->name ?? '').' &middot; '.e($feeAmount->fee?->mode ?? '').'</span>';
        };
    }

    /**
     * Build the `scope` column renderer (track / gender restriction).
     */
    private function scopeColumn(): \Closure
    {
        return function ($feeAmount) {
            $track = $feeAmount->admissionTrack?->name ?? 'Semua Jalur';
            $gender = $feeAmount->gender ? (FeeAmount::GENDERS[$feeAmount->gender] ?? $feeAmount->gender) : 'Semua Jender';
            $status = $feeAmount->childStatus?->name ?? 'Semua Status';

            return '<span class="text-body">'.e($track).'</span>'
                .'<span class="text-muted small d-block">'.e($gender).'</span>'
                .'<span class="text-muted small d-block">'.e($status).'</span>';
        };
    }

    /**
     * Build the `amount` renderer (rupiah formatting).
     */
    private function amountColumn(): \Closure
    {
        return function ($feeAmount) {
            return '<span class="fw-semibold text-body">Rp '.number_format($feeAmount->amount, 0, ',', '.').'</span>';
        };
    }

    /**
     * Build the `actions` column renderer.
     */
    private function actionsColumn(): \Closure
    {
        return function ($feeAmount) {
            // Carry the active list filters along, so saving or
            // deleting returns to the same filtered list.
            $returnQuery = collect(['school_unit_id', 'fee_component_id'])
                ->filter(fn ($key) => request()->filled($key))
                ->mapWithKeys(fn ($key) => [$key => request()->integer($key)])
                ->all();
            $returnSuffix = $returnQuery === [] ? '' : '?'.http_build_query($returnQuery);

            $html = '<div class="d-flex justify-content-end gap-2">';
            if (auth()->user()->can('fee-amount-edit')) {
                $html .= '<a href="'.route('admin.fee-amounts.edit', $feeAmount->id).$returnSuffix.'" class="btn btn-sm btn-light border" title="Ubah Fee Amount"><i class="bi bi-pencil-square text-primary"></i> Ubah</a>';
            }
            if (auth()->user()->can('fee-amount-delete')) {
                $html .= '<form action="'.route('admin.fee-amounts.destroy', $feeAmount->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Anda yakin ingin menghapus nominal biaya ini?\')">';
                foreach ($returnQuery as $key => $value) {
                    $html .= '<input type="hidden" name="return_'.$key.'" value="'.$value.'">';
                }
                $html .= csrf_field().method_field('DELETE');
                $html .= '<button type="submit" class="btn btn-sm btn-light border" title="Hapus Fee Amount"><i class="bi bi-trash text-danger"></i></button>';
                $html .= '</form>';
            }
            $html .= '</div>';

            return $html;
        };
    }
}
