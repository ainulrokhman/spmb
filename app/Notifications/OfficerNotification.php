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
            'url' => self::pathOnly($this->url),
            'icon' => $this->icon(),
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
