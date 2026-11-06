<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\ReadingPlanNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ユーザーは他のユーザーの読書計画を編集できない
     */
    public function testAnotherUserCannotUpdateReadingPlan(): void {
        // 1. ユーザーを作成し、そのユーザーに紐ついた読書計画を作成する
        // 2. 別ユーザーを作成し認証する
        // 3. GET /reading-plans/{plan}/editにアクセスする
        // 4. テスト:403Forbiddenが表示される
        // 5. PUT /reading-plans/{plan}にアクセスする
        // 6. テスト:403Forbiddenが表示され、編集に失敗する

        // 1
        $plan = $this->createReadingPlanTestData();

        // 実行前の読書計画の情報を保持しておく
        $origin = $plan->getAttributes();

        // 2,3,4
        $this->actingAs(User::factory()->create())
            ->get("/reading-plans/{$plan->id}/edit")
            ->assertForbidden();

        // 5,6
        $this->put("/reading-plans/{$plan->id}", [
            'target_date' => $plan->target_date->copy()->addDay()->toDateString(),
        ])->assertForbidden();

        $this->assertSame($origin, $plan->refresh()->getAttributes());
    }

    /**
     * ユーザーは他のユーザーの読書計画を読了にできない
     */
    public function testAnotherUserCannotCompleteReadingPlan(): void {
        // 1. ユーザーを作成し、そのユーザーに紐ついた読書計画を作成する
        // 2. 別ユーザーを作成し認証する
        // 3. POST /reading-plans/{plan}/completeにアクセスする
        // 4. テスト:403Forbiddenが表示され、DBの内容が変更されていない

        // 1
        $plan = $this->createReadingPlanTestData();

        // 実行前の読書計画の情報を保持しておく
        $origin = $plan->getAttributes();

        // 2,3,4
        $this->actingAs(User::factory()->create())
            ->post("/reading-plans/{$plan->id}/complete")
            ->assertForbidden();
        $this->assertSame($origin, $plan->refresh()->getAttributes());
    }

    /**
     * ユーザーは他のユーザーの読書計画を削除できない
     */
    public function testAnotherUserCannotDeleteReadingPlan(): void {
        // 1. ユーザーを作成し、そのユーザーに紐ついた読書計画を作成する
        // 2. 別ユーザーを作成し認証する
        // 3. DELETE /reading-plans/{plan}にアクセスする
        // 4. テスト:403Forbiddenが表示され、件数と対象の読書計画の内容が変更されていない

        // 1
        $plan = $this->createReadingPlanTestData();

        // 実行前の情報をテスト
        $origin = $plan->getAttributes();
        $planCount = $plan->newQuery()->count();

        // 2,3
        $this->actingAs(User::factory()->create())
            ->delete("/reading-plans/{$plan->id}")
            ->assertForbidden();

        // 4
        $this->assertDatabaseCount('reading_plans', $planCount);
        $remainingPlan = $plan->fresh();
        $this->assertNotNull($remainingPlan);
        $this->assertSame($origin, $remainingPlan->getAttributes());
    }

    /**
     * ユーザーは他のユーザーの通知を閲覧・既読更新できない
     */
    public function testAnotherUserCannotAccessNotfication(): void {
        // 1. ユーザーを作成し、そのユーザーに紐ついた読書計画を作成する
        // 2. 読書計画に対して未読の通知を作成する
        // 3. 別ユーザーを作成し認証する
        // 4. GET /notifications
        // 5. テスト: 通知無しが表示されていることを確認
        // 6. POST /notifications/{notification_id}/readにアクセスする
        // 7. テスト: 404NotFoundが表示され、対象通知のDB情報が既読になっていないことを確認

        // 1,2
        $plan = $this->createReadingPlanTestData();
        $plan->update(['target_date' => today()->toDateString()]);
        $owner = $plan->user;
        $owner->notify(new ReadingPlanNotification($plan));
        $notification = $owner->notifications()->firstOrFail();
        $this->assertNull($notification->read_at);
        $origin = $notification->getAttributes();

        // 3,4,5
        $this->actingAs(User::factory()->create())
            ->get('/notifications')
            ->assertOk()
            ->assertViewIs('notifications.index')
            ->assertViewHas('notifications', fn ($notifications) => $notifications->isEmpty())
            ->assertSeeText('通知はありません。')
            ->assertDontSee('action="' . route('notifications.read', $notification->id) . '"', false);

        // 6,7
        $response = $this->post('/notifications/' . $notification->id . '/read');
        $this->assertNull($notification->refresh()->read_at);
        $this->assertSame($origin, $notification->getAttributes());
        $response->assertNotFound();
    }

    /**
     * ゲストは読書計画の新規作成はできない
     */
    public function testGuestCannnotCreateReadingPlan(): void {
        // 1.書籍を作成する
        // 2.ゲストとしてPOST /reading-plans/createにアクセスする
        // 3.ログイン画面にリダイレクトされ、読書計画の登録に失敗する

        // 1
        $book = Book::factory()->create();

        // 2,3
        $this->post('/reading-plans/create', [
            'book_id' => $book->id,
            'target_date' => now()->toDateString(),
        ])
            ->assertRedirect('login');

        $this->assertDatabaseEmpty('reading_plans');
    }

    /**
     * 読書計画のテストデータを作成する
     *
     * @return Model
     */
    private function createReadingPlanTestData(): ReadingPlan {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $book->genres()->sync(Genre::create([
            'name' => '小説',
        ]));
        // デフォルト値が作成されないのでrefresh
        $plan = $user->readingPlans()->create([
            'book_id' => $book->id,
            'target_date' => now()->addDay(),
        ])->refresh();

        return $plan;
    }
}
