<?php

namespace App\Http\Controllers;

use App\Models\ReadingPlan;
use Illuminate\Http\Request;



class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return View('notifications.index', [
            'notifications' => $request->user()->notifications
        ]);
    }

    // /**
    //  * Show the form for creating a new resource.
    //  */
    // public function create()
    // {
    //     //
    // }

    // /**
    //  * Store a newly created resource in storage.
    //  */
    // public function store(Request $request)
    // {
    //     //
    // }

    // /**
    //  * Display the specified resource.
    //  */
    // public function show(ReadingPlan $readinPlan)
    // {
    //     //
    // }

    // /**
    //  * Show the form for editing the specified resource.
    //  */
    // public function edit(ReadingPlan $readinPlan)
    // {
    //     //
    // }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $notificationId)
    {
        // 認証ユーザーの対象通知を既読にする
        $request->user()
            ->notifications()
            ->findOrFail($notificationId)
            ->update([
                'read_at' => now()
            ]);

        return redirect(route('notifications.index'))->with('success', '通知を既読にしました。');
    }

    // /**
    //  * Remove the specified resource from storage.
    //  */
    // public function destroy(ReadingPlan $readinPlan)
    // {
    //     //
    // }
}
