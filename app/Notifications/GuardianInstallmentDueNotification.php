<?php

namespace App\Notifications;

/**
 * Reminder that an installment term is about to fall due (or falls due today).
 */
class GuardianInstallmentDueNotification extends GuardianNotification
{
    protected function icon(): string
    {
        return 'bi bi-alarm';
    }
}
