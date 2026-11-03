<?php

namespace App\Notifications;

/**
 * The committee decided the guardian's installment request.
 */
class GuardianInstallmentNotification extends GuardianNotification
{
    protected function icon(): string
    {
        return 'bi bi-calendar2-plus';
    }
}
