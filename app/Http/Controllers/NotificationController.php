<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View {
        return View('notifications.index', [
            'notifications' => $request->user()->notifications,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  string  $notificationId  既読更新対象の通知レコードID
     */
    public function update(Request $request, string $notificationId): RedirectResponse {
        // 認証ユーザーの対象通知を既読にする
        $request->user()
            ->notifications()
            ->findOrFail($notificationId)
            ->update([
                'read_at' => now(),
            ]);

        return redirect(route('notifications.index'))->with('success', '通知を既読にしました。');
    }
}
