<?php

namespace App\DataTables;

use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Yajra\DataTables\Facades\DataTables;

class FormPaymentDataTable
{
    /**
     * Build the DataTable response for the form payments list:
     * every application in every form-fee condition, urgent
     * work first (pending relief requests, then payments
     * awaiting confirmation). Committee members with unit
     * assignments are restricted by the controller through
     * the `unit_scope` value. Row actions follow the row's
     * state: confirm an awaiting manual payment, decide or
     * grant relief through the page's shared modal, view the
     * transfer proof, open the applicant detail.
     */
    public function make(): JsonResponse
    {
        $applications = Application::query()
            ->with(['user', 'schoolUnit', 'admissionWave.academicYear', 'admissionTrack', 'payments' => fn ($query) => $query->whereIn('status', ['paid', 'awaiting_confirmation'])->latest('id')])
            ->when(request()->filled('unit_scope'), function ($query) {
                $query->whereIn('school_unit_id', explode(',', (string) request()->input('unit_scope')));
            })
            ->when(request()->filled('school_unit_id'), fn ($query) => $query->where('school_unit_id', request()->integer('school_unit_id')))
            ->when(request()->filled('admission_wave_id'), fn ($query) => $query->where('admission_wave_id', request()->integer('admission_wave_id')))
            ->when(request()->filled('form_fee_status'), fn ($query) => $query->where('form_fee_status', (string) request()->input('form_fee_status')))
            ->when(request()->filled('channel'), function ($query) {
                $query->whereHas('payments', fn ($paymentQuery) => $paymentQuery
                    ->where('status', 'paid')
                    ->where('channel', (string) request()->input('channel')));
            })
            ->orderByRaw("CASE WHEN form_fee_request_status = 'pending' THEN 0 WHEN form_fee_status = 'awaiting_confirmation' THEN 1 ELSE 2 END")
            ->orderBy('updated_at');

        return DataTables::of($applications)
            ->addColumn('applicant', $this->applicantColumn())
            ->addColumn('unit', $this->unitColumn())
            ->addColumn('guardian', $this->guardianColumn())
            ->addColumn('fee', fn ($application) => $application->form_fee_amount === 0 ? 'Gratis' : 'Rp '.number_format($application->form_fee_amount, 0, ',', '.'))
            ->addColumn('discount', fn ($application) => $application->discountAmount() > 0 ? 'Rp '.number_format($application->discountAmount(), 0, ',', '.') : '—')
            ->addColumn('payable', fn ($application) => '<span class="fw-semibold">Rp '.number_format($application->payableAmount(), 0, ',', '.').'</span>')
            ->addColumn('fee_status', $this->feeStatusColumn())
            ->addColumn('payment_info', $this->paymentInfoColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['applicant', 'unit', 'guardian', 'payable', 'fee_status', 'payment_info', 'actions'])
            ->make(true);
    }

    private function applicantColumn(): \Closure
    {
        return function ($application) {
            return '<span class="fw-semibold text-body">'.e($application->child_name ?? '-').'</span>'
                .'<span class="text-muted small d-block">No. '.e($application->registration_number ?? '—').'</span>';
        };
    }

    private function unitColumn(): \Closure
    {
        return function ($application) {
            return '<span class="text-body">'.e($application->schoolUnit?->name ?? '-').'</span>'
                .'<span class="text-muted small d-block">'.e($application->admissionWave?->name ?? '').' ('.e($application->admissionWave?->academicYear?->name ?? '').') &middot; '.e($application->admissionTrack?->name ?? '').'</span>';
        };
    }

    private function guardianColumn(): \Closure
    {
        return function ($application) {
            return '<span class="text-body">'.e($application->user?->name ?? '-').'</span>'
                .'<span class="text-muted small d-block">'.e($application->user?->phone ?? '-').'</span>';
        };
    }

    private function feeStatusColumn(): \Closure
    {
        return function ($application) {
            $badgeClass = match ($application->form_fee_status) {
                'paid' => 'bg-success',
                'waived' => 'bg-primary',
                'free' => 'bg-secondary',
                'awaiting_confirmation' => 'bg-info text-dark',
                'awaiting_payment' => 'bg-warning text-dark',
                default => 'bg-danger',
            };

            $html = '<span class="badge '.$badgeClass.'">'.$application->feeStatusLabel().'</span>';

            if ($application->form_fee_request_status === 'pending') {
                $html .= '<span class="d-block text-warning small mt-1 fw-semibold">Pengajuan keringanan menunggu</span>';
            }

            return $html;
        };
    }

    private function paymentInfoColumn(): \Closure
    {
        return function ($application) {
            $settled = $application->payments->firstWhere('status', 'paid');

            if ($settled !== null) {
                return '<span class="text-body">'.$settled->channelLabel().'</span>'
                    .'<span class="text-muted small d-block">'.$settled->paid_at?->format('d/m/Y H:i').' &middot; '.e($settled->reference ?? '').'</span>';
            }

            $awaiting = $application->payments->firstWhere('status', 'awaiting_confirmation');

            if ($awaiting !== null) {
                return '<span class="text-body">'.$awaiting->channelLabel().'</span>'
                    .'<span class="text-muted small d-block">Dinyatakan '.e($awaiting->updated_at?->format('d/m/Y H:i') ?? '').'</span>';
            }

            return '—';
        };
    }

    private function actionsColumn(): \Closure
    {
        return function ($application) {
            $buttons = [];
            $canDecide = auth()->user()?->can('form-payment-decide') ?? false;
            $awaiting = $application->payments->firstWhere('status', 'awaiting_confirmation');

            if ($canDecide && $awaiting !== null) {
                if ($awaiting->proof_path) {
                    $buttons[] = '<a href="'.route('admin.payments.proof', $awaiting).'" target="_blank" rel="noopener" class="btn btn-light border btn-sm" title="Lihat Bukti Transfer"><i class="bi bi-receipt"></i></a>';
                }

                $buttons[] = '<form method="POST" action="'.route('admin.form-payments.confirm', $awaiting).'" onsubmit="return confirm(\'Konfirmasi pembayaran ini sebagai lunas?\');" class="d-inline">'
                    .csrf_field()
                    .'<button type="submit" class="btn btn-success btn-sm" title="Konfirmasi pembayaran sebagai lunas"><i class="bi bi-check-lg"></i> Konfirmasi</button></form>';
            }

            if ($canDecide && $application->form_fee_request_status === 'pending') {
                $buttons[] = '<button type="button" class="btn btn-warning btn-sm relief-action" data-mode="decide"'
                    .' data-url="'.route('admin.form-payments.decide', $application).'"'
                    .' data-child="'.e($application->child_name ?? '').'"'
                    .' data-reason="'.e($application->form_fee_request_reason ?? '').'"'
                    .' data-attachment="'.($application->form_fee_request_file_path ? route('admin.applications.relief-file', $application) : '').'"'
                    .' title="Putuskan pengajuan keringanan">Keringanan</button>';
            } elseif ($canDecide
                && $application->status === 'awaiting_form_fee'
                && $application->form_fee_status === 'unpaid'
                && $application->form_fee_discount_type === null) {
                $buttons[] = '<button type="button" class="btn btn-outline-primary btn-sm relief-action" data-mode="grant"'
                    .' data-url="'.route('admin.form-payments.grant', $application).'"'
                    .' data-child="'.e($application->child_name ?? '').'"'
                    .' data-reason="" data-attachment=""'
                    .' title="Beri keringanan tanpa pengajuan">Keringanan</button>';
            }

            $buttons[] = '<a href="'.route('admin.applications.show', $application).'" class="btn btn-sm btn-light border" title="Detail Pendaftar"><i class="bi bi-eye text-primary"></i></a>';

            return '<div class="d-flex gap-1 justify-content-end flex-wrap">'.implode('', $buttons).'</div>';
        };
    }
}
