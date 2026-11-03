<?php

namespace App\Notifications;

/**
 * A guardian requested relief on a form fee; the request
 * waits for a decision on the form payments worklist.
 */
class FormFeeReliefRequestedNotification extends OfficerNotification
{
    protected function icon(): string
    {
        return 'bi bi-percent';
    }
}
