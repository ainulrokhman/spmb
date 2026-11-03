<?php

namespace App\Http\Controllers\Guardian;

use App\Contracts\GuardianNotificationServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * The guardian's own notifications: a list page behind the
 * navbar bell. Opening one marks it read and continues to
 * the child's page it refers to. Result notifications stay
 * hidden — here as well — until the announcement time.
 */
class NotificationController extends Controller
{
    public function __construct(
        private readonly GuardianNotificationServiceInterface $guardianNotifications,
    ) {}

    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(20);

        $notifications->setCollection(
            $this->guardianNotifications->filterVisible($notifications->getCollection())
        );

        return view('guardian.notifications.index', compact('notifications'));
    }

    public function show(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id
            && $notification->notifiable_type === $request->user()::class, 404);

        abort_if($this->isHiddenResult($notification), 404);

        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return redirect()->to(is_string($url) && $url !== '' ? $url : route('guardian.notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        // Only the visible ones: a result notification still
        // behind the announcement gate must not be marked
        // read sight unseen.
        $this->guardianNotifications->visibleUnread($request->user())
            ->each(fn (DatabaseNotification $notification) => $notification->markAsRead());

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    /**
     * Whether the notification is a selection result whose
     * announcement time has not come yet.
     */
    private function isHiddenResult(DatabaseNotification $notification): bool
    {
        if (($notification->data['kind'] ?? null) !== 'result') {
            return false;
        }

        $application = Application::find($notification->data['application_id'] ?? null);

        return $application === null || ! $application->resultsAnnounced();
    }
}
