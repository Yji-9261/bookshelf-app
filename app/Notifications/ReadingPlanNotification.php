<?php

namespace App\Notifications;

use App\Models\ReadingPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;


class ReadingPlanNotification extends Notification
{
    use Queueable;

    private ReadingPlan $plan;

    /**
     * Create a new notification instance.
     */
    public function __construct(ReadingPlan $plan)
    {
        $this->plan = $plan;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    // public function toMail(object $notifiable): MailMessage
    // {
    //     return (new MailMessage)
    //         ->line('The introduction to the notification.')
    //         ->action('Notification Action', url('/'))
    //         ->line('Thank you for using our application!');
    // }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $title = $this->plan->book->title;

        // 符号付きで現在の日付と期限の差分を計算し、通知内容を変更する
        $diffDay = today()->diffInDays($this->plan->target_date, false);

        return match ($diffDay) {
            3 => ['timing' => 'three_days_before', 'title' => $title, 'body' => '期限三日前です'],
            0 => ['timing' => 'on_due_date', 'title' => $title, 'body' => '本日が期限です'],
            -3 => ['timing' => 'three_days_after', 'title' => $title, 'body' => '期限を三日過ぎています'],

            // 通知スケジュール仕様上存在しない仕様だが、念の為用意しておく
            default => ['timing' => null, 'title' => $title, 'body' => null]
        };
    }
    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [

        ];
    }
}
