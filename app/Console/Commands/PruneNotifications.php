<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Officer notifications are work signals, not an archive:
 * rows older than the retention window (default 90 days)
 * are deleted daily by the scheduler.
 */
class PruneNotifications extends Command
{
    protected $signature = 'notifications:prune {--days=90 : Delete notifications older than this many days}';

    protected $description = 'Delete officer notifications older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        $deleted = DB::table('notifications')
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Menghapus {$deleted} notifikasi yang lebih lama dari {$days} hari.");

        return self::SUCCESS;
    }
}
