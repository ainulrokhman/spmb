<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * The signed-in officer's own notifications (Fase 7): a list
 * page behind the topbar bell. Opening one marks it read and
 * continues to the page where the matter is handled.
 */
class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()->notifications()->paginate(20);

        return view('admin.notifications.index', compact('notifications'));
    }

    public function show(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === $request->user()->id
            && $notification->notifiable_type === $request->user()::class, 404);

        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return redirect()->to(is_string($url) && $url !== '' ? $url : route('admin.notifications.index'));
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
