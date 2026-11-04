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
            'url' => self::pathOnly($this->url),
            'icon' => $this->icon(),
            'application_id' => $this->applicationId,
            'kind' => $this->kind,
        ];
    }

    /**
     * Reduce a target URL to its path (plus query, when any):
     * the stored link must follow whatever domain the app is
     * currently served from — persisting an absolute URL would
     * freeze the APP_URL of creation time into the database
     * and strand every older notification on the old address
     * after a domain change.
     */
    private static function pathOnly(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query !== null && $query !== '' ? $path.'?'.$query : $path;
    }

    /**
     * Bootstrap icon shown next to the notification.
     */
    protected function icon(): string
    {
        return 'bi bi-bell';
    }
}
