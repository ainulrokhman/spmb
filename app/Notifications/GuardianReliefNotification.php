<?php

namespace App\Notifications;

/**
 * A committee relief decision (form fee or invoice) for the guardian's child.
 */
class GuardianReliefNotification extends GuardianNotification
{
    protected function icon(): string
    {
        return 'bi bi-percent';
    }
}
