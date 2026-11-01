<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request) {
        return View('notifications.index', [
            'notifications' => $request->user()->notifications
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $notificationId) {
        // 認証ユーザーの対象通知を既読にする
        $request->user()
            ->notifications()
            ->findOrFail($notificationId)
            ->update([
                'read_at' => now()
            ]);

        return redirect(route('notifications.index'))->with('success', '通知を既読にしました。');
    }
}
