<?php

namespace App\Notifications;

/**
 * A guardian requested relief on a re-registration invoice;
 * the request waits for a decision on the invoice page.
 */
class InvoiceReliefRequestedNotification extends OfficerNotification
{
    protected function icon(): string
    {
        return 'bi bi-percent';
    }
}
