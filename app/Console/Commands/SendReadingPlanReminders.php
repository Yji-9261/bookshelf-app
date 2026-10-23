<?php

namespace App\Console\Commands;

use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Console\Command;
//use Illuminate\Notifications\Notification;

use App\Notifications\ReadingPlanNotification;
use Illuminate\Support\Facades\Notification;

class SendReadingPlanReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-reading-plan-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '読書計画期限のリマインド通知を送るコマンド';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 読書計画期限。3日前,当日,3日後のものを取得
        $plans = ReadingPlan::whereDate('target_date', today())
            ->orWhereDate('target_date', today()->subDays(3))
            ->orWhereDate('target_date', today()->addDays(3))
            ->get();

        $plans->each(function ($plan) {
            Notification::send($plan->user, new ReadingPlanNotification($plan));
        });
    }
}
