<?php

namespace App\Notifications;

/**
 * The committee decided the verification of the child's application.
 */
class GuardianVerificationNotification extends GuardianNotification
{
    protected function icon(): string
    {
        return 'bi bi-patch-check';
    }
}
