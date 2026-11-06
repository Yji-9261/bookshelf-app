<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ReadingPlanNotification extends Notification
{
    use Queueable;

    /**
     * 期限３日前
     */
    const THREE_DAYS_BEFORE = 3;

    /**
     * 期限当日
     */
    const ON_DUE_DATE = 0;

    /**
     * 期限3日後
     */
    const THREE_DAYS_AFTER = -3;

    /**
     * 通知対象となる読書計画
     */
    private ReadingPlan $plan;

    /**
     * 期日が3日後・当日・3日前の読書計画を通知対象として受け取る
     */
    public function __construct(ReadingPlan $plan) {
        $this->plan = $plan;
    }

    /**
     * 通知チャネルとしてデータベースを指定する
     */
    public function via(object $notifiable): array {
        return ['database'];
    }

    /**
     * 通知タイミング、対象書籍のタイトル、通知内容を返す
     */
    public function toDatabase(object $notifiable): array {
        $title = $this->plan->book->title;

        // 符号付きで現在の日付と期限の差分を計算し、通知内容を変更する
        $diffDay = today()->diffInDays($this->plan->target_date, false);

        return match ($diffDay) {
            self::THREE_DAYS_BEFORE => ['timing' => 'three_days_before', 'title' => $title, 'body' => '期限三日前です'],
            self::ON_DUE_DATE => ['timing' => 'on_due_date', 'title' => $title, 'body' => '本日が期限です'],
            self::THREE_DAYS_AFTER => ['timing' => 'three_days_after', 'title' => $title, 'body' => '期限を三日過ぎています'],
        };
    }
}
