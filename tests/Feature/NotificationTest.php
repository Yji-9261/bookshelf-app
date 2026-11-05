<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void {
        parent::setUp();

        Carbon::setTestNow();
    }

    /**
     * ゲストが通知一覧にアクセスするとログイン画面にリダイレクトされる
     */
    public function testGuestCannotAccess(): void {
        $this->get('/notifications')->assertRedirect('/login');
    }

    /**
     * 読書計画期限3日前の書籍が表示される
     */
    public function testNotificationThreeBeforeDay(): void {
        // 1. 期限三日前の読書計画を作成する
        // 2. app:send-reading-plan-remindersコマンドを実行する
        // 3. テスト: 「期限三日前です」を含んだ内容が表示されている
        // 4. テスト: 通知テーブルに一件登録されたことを確認する

        // 1
        $title = 'テスト';
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => $title]);
        $user->readingPlans()->create([
            'book_id' => $book->id,
            'target_date' => Carbon::now()->addDays(3)->toDateString(),
        ]);

        // まだDBに登録されていないことを検証しておく
        $this->assertSame(0, $user->notifications()->count());

        // 2
        $this->artisan('app:send-reading-plan-reminders')->assertExitCode(0);

        // 3
        $this->accessNoitificationPage($user)->assertSeeTextInOrder([
            '未読',
            $book->title,
            '期限三日前です',
            '既読にする',
        ]);

        // 4
        $this->assertSame(1, $user->notifications()->count());

    }

    /**
     * 読書計画期限当日の書籍が表示される
     */
    public function testNotificationDueToday(): void {
        // 1. 期限当日の読書計画を作成する
        // 2. app:send-reading-plan-remindersコマンドを実行する
        // 3. テスト: 「本日が期限です」を含んだ内容が表示されている
        // 4. テスト: 通知テーブルに一件登録されたことを確認する

        // 1
        $title = 'テスト';
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => $title]);
        $user->readingPlans()->create([
            'book_id' => $book->id,
            'target_date' => Carbon::now()->toDateString(),
        ]);

        // まだDBに登録されていないことを検証しておく
        $this->assertSame(0, $user->notifications()->count());

        // 2
        $this->artisan('app:send-reading-plan-reminders')->assertExitCode(0);

        // 3
        $this->accessNoitificationPage($user)->assertSeeTextInOrder([
            '未読',
            $title,
            '本日が期限です',
            '既読にする',
        ]);

        // 4
        $this->assertSame(1, $user->notifications()->count());
    }

    /**
     * 読書計画期限3日後の書籍が表示される
     */
    public function testNotificationThreeDayAfter(): void {
        // 1. 期限3日後の読書計画を作成する
        // 2. app:send-reading-plan-remindersコマンドを実行する
        // 3. テスト: 「期限を三日過ぎています」を含んだ内容が表示されている
        // 4. テスト: 通知テーブルに一件登録されたことを確認する

        // 1
        $title = 'テスト';
        $user = User::factory()->create();
        $book = Book::factory()->create(['title' => $title]);
        $user->readingPlans()->create([
            'book_id' => $book->id,
            'target_date' => Carbon::now()->subDays(3)->toDateString(),
        ]);

        // まだDBに登録されていないことを検証しておく
        $this->assertSame(0, $user->notifications()->count());

        // 2
        $this->artisan('app:send-reading-plan-reminders')->assertExitCode(0);

        // 3
        $this->accessNoitificationPage($user)->assertSeeTextInOrder([
            '未読',
            $title,
            '期限を三日過ぎています',
            '既読にする',
        ]);

        // 4
        $this->assertSame(1, $user->notifications()->count());
    }

    /**
     * 当日、３日前、３日超以外で通知は表示されない
     */
    public function testNoNotificationOutsideReminderDates(): void {
        // 1. 当日、3日前、3日後を除く前後8日間を期日とした読書計画を作成する
        // 2. app:send-reading-plan-remindersコマンドを実行する
        // 3. テスト: コマンドが実行されていないことを確認する
        // 4. テスト: 通知テーブルに一件も登録されていないことを確認する
        // 5. テスト: 「通知はありません」が表示されている

        // 1
        $tagetDates = [
            now()->subDays(4), // 4日前
            now()->subDays(2), // 2日前
            now()->subDays(1), // 1日前
            now()->addDays(1), // 1日後
            now()->addDays(2), // 2日後
            now()->addDays(4), // 4日後
        ];

        $user = User::factory()->create();
        Book::factory()->count(count($tagetDates))->create();

        foreach ($tagetDates as $index => $taget_date) {
            $user->readingPlans()->create([
                'book_id' => $index + 1,
                'target_date' => $taget_date->toDateString(),
            ]);
        }

        // 2,3 実行コード-1は実行対象の読書計画がない状態
        $this->artisan('app:send-reading-plan-reminders')->assertExitCode(0);

        // 4
        $this->assertSame(0, $user->notifications()->count());

        // 5
        $this->accessNoitificationPage($user)
            ->assertSeeText(
                '通知はありません。'
            );
    }

    /**
     * 既読状態にできる
     */
    public function testNotificationCanMarkAsRead(): void {
        // 1. 通知対象の読書計画データを作成する
        // 2. 通知コマンドを実行する
        // 3. テスト: 既読済みでない通知がDBに登録されたことを確認
        // 4. 既読にするアクションをPOST送信する
        // 5. テスト: 既読にするボタンが表示されていないことを確認
        // 6. テスト: 既読済み通知がDBに登録されたことを確認

        // 1
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->readingPlans()->create([
            'book_id' => $book->id,
            'target_date' => Carbon::now()->subDays(3)->toDateString(),
        ])->refresh();

        // 通知がないことをあらかじめ確認する
        $this->assertSame(0, $user->notifications()->count());

        // 2
        $this->artisan('app:send-reading-plan-reminders')->assertExitCode(0);

        // 3
        $this->assertSame(1, $user->notifications()->count());
        $notification = $user->notifications()->firstOrFail();
        $this->assertNull($notification->read_at);

        // 4
        // 既読にするPOST先アドレスが存在するか確認してからPOST実行
        $this->accessNoitificationPage($user)
            ->assertSee("/notifications/{$notification->id}/read");
        $this->post("/notifications/{$notification->id}/read")
            ->assertRedirect('/notifications');

        // モデル内のeager loadingが原因で想定通りの結果とならないので再度読み直す
        $user->load('notifications');
        $notification->refresh();

        // 5
        $this->get('/notifications')->assertDontSeeText('既読にする');

        // 6
        $this->assertNotNull($notification->read_at);
    }

    /**
     * scheduleが設定通りの時刻に実行される
     */
    public function testCommandSchedule(): void {
        // 1. 基準時間を00:00にする
        // 2. 通知対象と期限切れ更新対象の読書計画を作成する
        // 3. テスト: 通知・期限切れコマンドがそれぞれ一件だけスケジュール登録されていることを検証する
        // 4. テスト: 00:00にコマンドが実行対象となることを確認する
        // 5. テスト: 00:01にコマンドが実行対象でないことを確認する
        // 6. テスト: 01:00~23:00にコマンドが実行対象でないことを確認する
        // 7. テスト: 23:59にコマンドが実行対象でないことを確認する

        // 1
        Carbon::setTestNow('2026-01-01 00:00:00');

        // 2
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $user->readingPlans()->create([
            'book_id' => $book->id,
            'target_date' => Carbon::now()->subDays(3)->toDateString(),
        ])->refresh();

        $expiredPlan = $user->readingPlans()->create([
            'book_id' => Book::factory()->create()->id,
            'target_date' => Carbon::now()->subDays(4)->toDateString(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        // 3
        $commands = [
            'app:send-reading-plan-reminders',
            'app:expire-reading-plan',
        ];
        $scheduledEvents = collect(app(Schedule::class)->events());
        $events = collect($commands)->mapWithKeys(function ($command) use ($scheduledEvents) {
            $matches = $scheduledEvents->filter(
                fn ($event) => str_contains($event->command ?? '', $command)
            );
            $this->assertCount(1, $matches, $command);

            return [$command => $matches->first()];
        });

        // schedule:runは別プロセスを起動し、SQLiteのメモリDB・固定日時を共有できないとのこと
        // 登録済みイベントの実行時刻を判定し、コマンドだけを同一プロセスで実行する。
        $runScheduledCommand = function (bool $expectedDue) use ($events): void {
            foreach ($events as $command => $event) {
                $isDue = $event->isDue($this->app);
                // 想定していない時刻にコマンド実行されたらコマンド名と日時をエラー表示する
                $this->assertSame($expectedDue, $isDue, $command . ' ' . now()->toDateTimeString());

                if ($isDue) {
                    $this->artisan($command)->assertSuccessful();
                }
            }
        };

        // あらかじめ通知が0件であることを検証しておく
        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(ReadingPlanStatus::InProgress, $expiredPlan->refresh()->status);

        // 4
        $time = Carbon::now();
        $runScheduledCommand(true);
        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame(ReadingPlanStatus::Expired, $expiredPlan->refresh()->status);

        // 5
        $this->travelTo($time->copy()->addMinute());
        $runScheduledCommand(false);

        // 6
        for ($hour = 1; $hour <= 23; $hour++) {
            $this->travelTo($time->copy()->addHours($hour));
            $runScheduledCommand(false);
        }

        // 7
        $this->travelTo($time->copy()->setTime(23, 59));
        $runScheduledCommand(false);

        // 実行対象外の時刻では通知が追加されないことを検証
        $this->assertSame(1, $user->notifications()->count());
    }

    /**
     * 認証ユーザーとして通知一覧画面に正常にアクセスできるか検証する
     */
    private function accessNoitificationPage(User $user): TestResponse {
        return $this->actingAs($user)
            ->get('/notifications')
            ->assertOk()
            ->assertViewIs('notifications.index');
    }
}
