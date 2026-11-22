<?php

namespace App\Console\Commands;

use App\Models\Application;
use App\Services\Whatsapp\WhatsappNotificationService;
use Illuminate\Console\Command;

/**
 * WhatsApp selection-result announcements (WhatsApp Tahap 3).
 * Results are decided by the committee before they are
 * announced, and WhatsApp must never leak one early — so
 * this sweep only messages applications whose announcement
 * time has arrived; the notification service's log guard
 * makes every result go out exactly once.
 */
class SendAnnouncedResultNotifications extends Command
{
    protected $signature = 'whatsapp:notify-results';

    protected $description = 'Kirim WhatsApp hasil seleksi yang sudah tiba waktu pengumumannya';

    public function handle(WhatsappNotificationService $notifications): int
    {
        $processed = 0;

        Application::query()
            ->whereIn('status', ['passed', 'failed'])
            ->with(['user', 'schoolUnit'])
            ->chunkById(100, function ($applications) use ($notifications, &$processed) {
                foreach ($applications as $application) {
                    if (! $application->resultsAnnounced()) {
                        continue;
                    }

                    $notifications->resultAnnounced($application);
                    $processed++;
                }
            });

        $this->info("Hasil seleksi yang diproses untuk WhatsApp: {$processed}");

        return self::SUCCESS;
    }
}
