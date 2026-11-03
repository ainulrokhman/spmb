<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One component slice of an installment term: how much of
 * the term pays the given invoice item (component). Across
 * all terms of a plan, the slices of one component always
 * total that component's payable share of the invoice.
 */
class InvoiceInstallmentItem extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceInstallmentItemFactory> */
    use HasFactory;

    protected $fillable = [
        'invoice_installment_id',
        'invoice_item_id',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
        ];
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(InvoiceInstallment::class, 'invoice_installment_id');
    }

    public function invoiceItem(): BelongsTo
    {
        return $this->belongsTo(InvoiceItem::class);
    }
}
