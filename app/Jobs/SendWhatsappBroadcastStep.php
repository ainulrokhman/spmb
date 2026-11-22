<?php

namespace App\Jobs;

use App\Models\WhatsappBroadcast;
use App\Services\Whatsapp\WhatsappBroadcastService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * One step of a WhatsApp broadcast: send to the next pending
 * recipient. The service chains the following step with a
 * random delay, so pausing or cancelling the broadcast stops
 * the chain between messages. Needs a running queue worker.
 */
class SendWhatsappBroadcastStep implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $broadcastId) {}

    public function handle(WhatsappBroadcastService $service): void
    {
        $broadcast = WhatsappBroadcast::find($this->broadcastId);

        if ($broadcast !== null) {
            $service->processNext($broadcast);
        }
    }
}
