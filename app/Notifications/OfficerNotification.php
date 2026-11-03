<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Base class for officer (petugas) task notifications: stored
 * through the database channel only, rendered by the admin
 * bell and the notification list page. The event key groups
 * every officer's copy of the same underlying request, so
 * deciding that request can mark all copies read at once.
 */
abstract class OfficerNotification extends Notification
{
    public function __construct(
        protected readonly string $eventKey,
        protected readonly string $title,
        protected readonly string $message,
        protected readonly string $url,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_key' => $this->eventKey,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'icon' => $this->icon(),
        ];
    }

    /**
     * Bootstrap icon shown next to the notification.
     */
    protected function icon(): string
    {
        return 'bi bi-bell';
    }
}
