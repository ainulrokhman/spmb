<?php

namespace App\Notifications;

/**
 * The selection result (Lulus / Tidak Lulus) of the child; held back until the announcement time.
 */
class GuardianResultNotification extends GuardianNotification
{
    protected function icon(): string
    {
        return 'bi bi-trophy';
    }
}
