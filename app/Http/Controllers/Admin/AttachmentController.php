<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\Payment;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the private workflow attachments (Fase 5): manual
 * payment proofs and relief-request supporting documents.
 * Files live on the private disk — every response checks that
 * the user may access the owning school unit first.
 */
class AttachmentController extends Controller
{
    /**
     * The transfer proof a guardian uploaded for a manual
     * payment (form fee, invoice, or installment term).
     */
    public function paymentProof(Payment $payment)
    {
        $payable = $payment->payable;

        $unitId = match (true) {
            $payable instanceof Application => $payable->school_unit_id,
            $payable instanceof Invoice => $payable->application?->school_unit_id,
            $payable instanceof InvoiceInstallment => $payable->invoice?->application?->school_unit_id,
            default => null,
        };

        abort_if($unitId === null || ! auth()->user()->hasUnitAccess($unitId), 403);

        return $this->serve($payment->proof_path);
    }

    /**
     * The supporting document of a form fee relief request.
     */
    public function formFeeRelief(Application $application)
    {
        abort_unless(auth()->user()->hasUnitAccess($application->school_unit_id), 403);

        return $this->serve($application->form_fee_request_file_path);
    }

    /**
     * The supporting document of an invoice relief request.
     */
    public function invoiceRelief(Invoice $invoice)
    {
        abort_unless(auth()->user()->hasUnitAccess($invoice->application?->school_unit_id), 403);

        return $this->serve($invoice->request_file_path);
    }

    /**
     * Stream a private-disk file inline (so images and PDFs open
     * in the browser), or 404 when there is nothing to show.
     */
    private function serve(?string $path)
    {
        abort_if($path === null || ! Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
