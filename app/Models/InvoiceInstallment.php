<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One term (termin) of an invoice installment plan. The committee
 * details the terms; the guardian pays each term in full through
 * the regular payment channels (the term is the payment's
 * polymorphic payable).
 */
class InvoiceInstallment extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceInstallmentFactory> */
    use HasFactory;

    /**
     * Term payment states (same vocabulary as invoices).
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
        'invoice_id',
        'sequence',
        'amount',
        'due_date',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
