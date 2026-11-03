<?php

namespace App\Notifications;

/**
 * A payment state change: confirmed, completed, or unsuccessful.
 */
class GuardianPaymentNotification extends GuardianNotification
{
    protected function icon(): string
    {
        return 'bi bi-cash-coin';
    }
}
