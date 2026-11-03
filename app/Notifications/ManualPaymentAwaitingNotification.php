<?php

namespace App\Notifications;

/**
 * A guardian declared a manual payment (form fee, invoice,
 * or installment term); it waits for an officer to confirm
 * it against the transferred funds.
 */
class ManualPaymentAwaitingNotification extends OfficerNotification
{
    protected function icon(): string
    {
        return 'bi bi-cash-stack';
    }
}
