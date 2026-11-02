<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    /**
     * Invoice payment states (same vocabulary as the form fee).
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        'unpaid' => 'Belum Bayar',
        'awaiting_payment' => 'Menunggu Pembayaran',
        'awaiting_confirmation' => 'Menunggu Konfirmasi',
        'paid' => 'Lunas',
    ];

    protected $fillable = [
        'application_id',
        'total',
        'status',
        'discount_type',
        'discount_value',
        'request_kind',
        'request_type',
        'request_value',
        'request_reason',
        'request_status',
        'decision_note',
        'decided_by',
        'decided_at',
        'installment_request_reason',
        'installment_request_status',
        'installment_decision_note',
        'installment_decided_by',
        'installment_decided_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'installment_decided_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    /**
     * The installment terms of this invoice, in payment order.
     */
    public function installments(): HasMany
    {
        return $this->hasMany(InvoiceInstallment::class)->orderBy('sequence');
    }

    public function installmentDecider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installment_decided_by');
    }

    /**
     * Whether the committee has detailed an installment plan.
     */
    public function hasInstallmentPlan(): bool
    {
        return $this->installments()->exists();
    }

    /**
     * Total actually received for this invoice: direct payments
     * plus payments of its installment terms.
     */
    public function paidAmount(): int
    {
        $own = (int) $this->payments()->where('status', 'paid')->sum('amount');

        $viaInstallments = (int) Payment::query()
            ->where('status', 'paid')
            ->where('payable_type', InvoiceInstallment::class)
            ->whereIn('payable_id', $this->installments()->select('id'))
            ->sum('amount');

        return $own + $viaInstallments;
    }

    /**
     * The amount still outstanding after discounts and payments.
     */
    public function remainingAmount(): int
    {
        return max(0, $this->payableAmount() - $this->paidAmount());
    }

    /**
     * The discount amount in rupiah granted on this invoice.
     */
    public function discountAmount(): int
    {
        if ($this->discount_type === 'percent' && $this->discount_value) {
            return intdiv($this->total * $this->discount_value, 100);
        }

        if ($this->discount_type === 'nominal' && $this->discount_value) {
            return min($this->discount_value, $this->total);
        }

        return 0;
    }

    /**
     * The amount the guardian still has to pay.
     */
    public function payableAmount(): int
    {
        return max(0, $this->total - $this->discountAmount());
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
