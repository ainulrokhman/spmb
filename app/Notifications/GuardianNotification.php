<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Base class for guardian (wali) notifications: stored through
 * the database channel only, rendered by the guardian bell and
 * the notification list page. The payload carries the
 * application id and a kind so result notifications can be
 * held back until the announcement time (see the guardian
 * notification service's visibility filter).
 */
abstract class GuardianNotification extends Notification
{
    public function __construct(
        protected readonly string $eventKey,
        protected readonly string $title,
        protected readonly string $message,
        protected readonly string $url,
        protected readonly int $applicationId,
        protected readonly string $kind = 'general',
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_key' => $this->eventKey,
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            'icon' => $this->icon(),
            'application_id' => $this->applicationId,
            'kind' => $this->kind,
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
