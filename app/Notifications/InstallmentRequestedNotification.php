<?php

namespace App\Notifications;

/**
 * A guardian requested an installment plan for a
 * re-registration invoice; the committee details and decides
 * it on the invoice page.
 */
class InstallmentRequestedNotification extends OfficerNotification
{
    protected function icon(): string
    {
        return 'bi bi-calendar2-week';
    }
}
