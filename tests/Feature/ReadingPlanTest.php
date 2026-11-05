<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\User;
use Database\Seeders\BookSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\ReadingPlanSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ゲストは読書計画一覧画面に遷移せずログイン画面にリダイレクトされる
     */
    public function testGuestCannontAccess(): void {
        // 1. ゲストとして GET /reading-plansにアクセス
        // 2. テスト: ログイン画面にリダイレクトされる
        $this->get('/reading-plans')->assertRedirect('/login');
    }

    /**
     * 読書計画一覧画面が正常に表示される
     */
    public function testIndexView(): void {
        // 1. シーダーからテスト用データ作成
        // 2. 認証ユーザーとしてGET /reading-plansにアクセス
        // 3. テスト: DB登録順に[書籍, 期日, 完了日、状態、操作]項目が適切に表示されているか?

        $this->createTestDatasFromSeeder();
        $user = User::findOrFail(1);

        // DB 登録順に想定通りの表示となっているか
        $comparedStrings = $user->readingPlans
            ->map(function ($plan) {
                $comparedStrings = [
                    // 書籍
                    $plan->book->title,
                    // 期日
                    $plan->target_date->toDateString(),
                    // 完了日
                    $plan->completed_at?->toDateString() ?? '-',
                    // 状態
                    $plan->status === ReadingPlanStatus::InProgress ? '読書中' : '読了',
                ];

                // 読書中状態なら「読了する」と「編集」が表示される想定
                if ($plan->status === ReadingPlanStatus::InProgress) {
                    $comparedStrings[] = '読了する';
                    $comparedStrings[] = '編集';
                }
                $comparedStrings[] = '削除';

                return $comparedStrings;
            })
            ->flatten()
            ->toArray();

        // 2~7
        $this->actingAs($user)->get('/reading-plans')
            ->assertOk()
            ->assertViewIs('reading-plans.index')
            ->assertSeeTextInOrder($comparedStrings);
    }

    /**
     * 読書計画状態フィルタを実行した時に、期待通りの結果を返す
     */
    public function testStatusFiltering(): void {
        // 1. seederでテストデータを作成
        // 2. 状態「読書中」でフィルタリング
        // 3. テスト: 状態読書中の書籍が表示されている、読了書籍が表示されていない
        // 4. 状態「読了」でフィルタリング
        // 5. テスト: 状態読書中の書籍が表示されていない、読了書籍が表示されている

        // 1
        $this->createTestDatasFromSeeder();
        $user = User::findOrFail(1);

        // 2
        $response = $this->actingAs($user)
            ->get('/reading-plans?status=in_progress')
            ->assertOk();

        $inProgressPlans = $user->readingPlans()->whereNull('completed_at')->get();
        $completedPlans = $user->readingPlans()->whereNotNull('completed_at')->get();

        // 3
        $response->assertSeeTextInOrder(
            $inProgressPlans->map(function ($plan) {
                return $plan->book->title;
            })->toArray()
        );
        $completedPlans->each(function ($plan) use ($response) {
            $response->assertDontSeeText($plan->book->title);
        });

        // 4
        $response = $this
            ->get('/reading-plans?status=completed')
            ->assertOk();

        // 5
        $response
            ->assertSeeTextInOrder(
                $completedPlans->map(function ($plan) {
                    return $plan->book->title;
                })->toArray()
            );

        $inProgressPlans->each(function ($plan) use ($response) {
            $response->assertDontSeeText($plan->book->title);
        });
    }

    /**
     * 新規計画作成ボタンクリックすると読書計画登録画面に遷移する
     */
    public function testClickRegister(): void {
        // 1. seederからテストデータを作成
        // 2. 認証ユーザーとして読書計画作成画面にアクセス
        // 3. テスト: 読書計画作成画面に遷移する
        // 4. テスト: 登録済みの書籍（著者名）が一覧で表示されている

        // 1
        $this->createTestDatasFromSeeder();

        // 検証データ
        $titles = Book::all()->map(function ($book) {
            return $book->title . "（{$book->author}）";
        })->toArray();

        // 2,3,4
        $this->actingAs(User::factory()->create())
            ->get('/reading-plans/create')
            ->assertOk()
            ->assertViewIs('reading-plans.create')
            ->assertSeeInOrder($titles);
    }

    /**
     * 正常な入力で新規計画登録できる
     */
    public function testRegisterSuccess(): void {
        // 1. テストデータをseederから作成
        // 2. 新規ユーザー作成
        // 3. 作成したユーザーで認証
        // 4. POSTデータとして{book_id:1, target_date: '2026-01-01'}で '/reading-plans/create'に POST送信
        // 5. テスト: DBに {book_id:1, target_date: '2026-01-01'}で登録されている

        // 1
        $this->createTestDatasFromSeeder();

        // 2
        $user = User::factory()->create();

        // 現在のDB登録数が0であることを検証
        $this->assertSame(0, $user->readingPlans()->count());

        // 3,4
        $this->actingAs($user)->from('/reading-plans')
            ->post('/reading-plans/create', [
                'book_id' => 1,
                'target_date' => '2026-01-01',
            ]);

        // 5
        $this->assertSame(1, $user->readingPlans()->count());
        $readingPlans = $user->readingPlans()->first();
        $this->assertSame(1, $readingPlans->book_id);
        $this->assertSame('2026-01-01', $readingPlans->target_date->toDateString());
    }

    /**
     * 読書計画登録画面で不正な入力を行い登録を実行すると、登録に失敗する
     */
    public function testReadingPlanCreateInvalid(): void {
        // 1. テストデータをseederから作成
        // 2. 新規ユーザー作成
        // 3. 作成したユーザーで認証
        // 4. POSTデータとして{book_id:null, target_date: null}で '/reading-plans/create'に POST送信
        // 5. テスト: '/reading-plans/create'にリダイレクトされ、「書籍を入力してください」、「期日を入力してください」が表示される
        // 6. テスト: DBの登録に失敗する

        // 1
        $this->createTestDatasFromSeeder();
        $user = User::factory()->create();
        $createUrl = '/reading-plans/create';

        // 作成前のDB個数検証
        $this->assertSame(0, $user->readingPlans()->count());

        // 2
        $this->actingAs($user);

        // 3,4
        $this->checkInvalidState(
            $createUrl,
            $createUrl,
            'POST',
            [
                'book_id' => null,
                'target_date' => null,
            ],
            [
                'book_id' => '書籍を入力してください',
                'target_date' => '期日を入力してください',
            ],
        );

        // 5
        $this->assertSame(0, $user->readingPlans()->count());
    }

    /**
     * 編集ボタンクリックすると、対象の読書計画内容が反映されたページが表示される
     */
    public function testClickEdit(): void {
        // テスト用
        $this->createTestDatasFromSeeder();
        $user = User::findOrFail(1);
        $readingPlan = $user->readingPlans()->findOrFail(1);
        $editUrl = '/reading-plans/1/edit';

        // 編集するボタンのリンク先が存在する
        $this->actingAs($user)->get('/reading-plans')
            ->assertOk()
            ->assertViewIs('reading-plans.index')
            ->assertSee($editUrl, false);

        // リンク先アクセスし期待される表示物があるか？
        $this->get($editUrl)
            ->assertOk()
            ->assertViewIs('reading-plans.edit')
            ->assertSeeInOrder([
                $readingPlan->book->title,
                $readingPlan->status->label(),
                $readingPlan->target_date->toDateString(),
            ]);
    }

    /**
     * 読書計画編集画面で正常な入力を行い更新を実行すると、更新に成功する
     */
    public function testReadingPlanUpdateSuccess(): void {
        // 1. テスト用読書計画を作成{'target_date': '2026-01-01'}
        // 2. 読書計画作成者が認証ユーザーとする
        // 3. PUTデータとして{'target_date': '2026-01-02'} を　PUT /reading-plans/{$readingPlan->id}送信
        // 4. テスト: 読書計画一覧にリダイレクトされることを確認
        // 5. テスト: DBが正常に更新されていることを確認

        // 1
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $readingPlan = $user->readingPlans()->create([
            'book_id' => $book->id,
            'target_date' => '2026-01-01',
        ]);
        $updateUrl = "/reading-plans/{$readingPlan->id}";

        // PUT送信前のtarget_dateを検証しておく
        $this->assertSame('2026-01-01', $readingPlan->target_date->toDateString());

        // 2,3,4
        $this->actingAs($user)->put($updateUrl, ['target_date' => '2026-01-02'])
            ->assertRedirect('/reading-plans');

        // 5
        $this->assertSame('2026-01-02', $readingPlan->fresh()->target_date->toDateString());
    }

    /**
     * 読書計画編集画面で不正な入力を行い更新を実行すると、更新に失敗する
     */
    public function testReadingPlanUpdateInvalid(): void {
        // 1. テスト用読書計画を作成{'target_date': '2026-01-01'}
        // 2. 読書計画作成者が認証ユーザーとする
        // 3. PUTデータとして{'target_date': null} を　PUT /reading-plans/{$readingPlan->id}送信
        // 4. テスト: 読書計画編集にリダイレクトされる「期日を入力してください」が表示される
        // 5. テスト: DBに変更がないことを確認
        // 6. PUTデータとして{'target_date': '-01-01'} を　PUT /reading-plans/{$readingPlan->id}送信
        // 7. テスト: 読書計画編集にリダイレクトされる「yyyy-mm-dd形式で入力してください」が表示される
        // 8. テスト: DBに変更がないことを確認

        // 1
        $user = User::factory()->create();
        $book = Book::factory()->create();

        // デフォルト値はcreate段階でモデルに反映されないのでfreshを呼ぶ
        $readingPlan = $user->readingPlans()->create([
            'book_id' => $book->id,
            'target_date' => '2026-01-01',
        ])->fresh();

        $editUrl = "/reading-plans/{$readingPlan->id}/edit";
        $updateUrl = "/reading-plans/{$readingPlan->id}";

        // PUT送信前の状態を取得
        $original = $readingPlan->getAttributes();

        // 2
        $this->actingAs($user);
        // 3,4
        $this->checkInvalidState(
            $editUrl,
            $updateUrl,
            'PUT',
            [
                'target_date' => null,
            ],
            [
                'target_date' => '期日を入力してください',
            ],
        );
        // 5
        $this->assertSame($original, $readingPlan->fresh()->getAttributes());

        // 6,7
        $this->checkInvalidState(
            $editUrl,
            $updateUrl,
            'PUT',
            [
                'target_date' => '-01-01',
            ],
            [
                'target_date' => '期日をyyyy-mm-dd形式で入力してください',
            ],
        );

        // 8
        $this->assertSame($original, $readingPlan->fresh()->getAttributes());
    }

    /**
     * 読了ボタンクリックすると、読書計画が読了状態になる
     */
    public function testClickComplete(): void {
        // 1. seederからテストデータ作成
        // 2. 認証ユーザーとして読書計画一覧画面にアクセス
        // 3. テスト: 読了リンクがあるか
        // 4. 読了リンク先にPOST送信
        // 5. テスト: ReadinPlansテーブルの対象レコードが適切に更新されているか？
        // 6. テスト: 読了リンクがなくなっている

        // 1
        $this->createTestDatasFromSeeder();
        $user = User::findOrFail(1);
        $plan = $user->readingPlans()->findOrFail(1);
        $completeUrl = "/reading-plans/{$plan->id}/complete";

        // 現在の読書計画状態をテスト
        $this->assertNull($plan->completed_at);
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);

        // 2,3
        $this->actingAs($user)->get('/reading-plans')
            ->assertOk()
            ->assertViewIs('reading-plans.index')
            ->assertSee($completeUrl, false);

        // 4
        $this->post($completeUrl)
            ->assertRedirect('/reading-plans');

        // 5
        $plan->refresh();
        $this->assertSame(now()->toDateString(), $plan->completed_at->toDateString());
        $this->assertSame(ReadingPlanStatus::Completed, $plan->status);

        // 6
        $this->get('/reading-plans')
            ->assertDontSee($completeUrl);
    }

    /**
     * 削除ボタンクリックすると、読書計画が削除される
     */
    public function testClickDelete(): void {
        // 1. seederからテストデータ作成
        // 2. 認証ユーザーとして読書計画一覧画面にアクセス
        // 3. テスト: 削除リンクがあるか
        // 4. 削除リンク先にDELETE送信
        // 5. テスト: ReadinPlansテーブルの対象レコードが適切に削除されているか？
        // 6. テスト: 削除リンクがなくなっている

        // 1
        $this->createTestDatasFromSeeder();
        $user = User::findOrFail(1);
        $plan = $user->readingPlans()->find(1);
        $deleteUrl = "/reading-plans/{$plan->id}";

        // 削除対象の読書計画がまだ存在しているか検証
        $this->assertNotNull($plan);

        // 2,3
        $this->actingAs($user)->get('/reading-plans')
            ->assertOk()
            ->assertViewIs('reading-plans.index')
            ->assertSee($deleteUrl, false);

        // 4
        $this->delete($deleteUrl)
            ->assertRedirect('/reading-plans');

        // 5
        $plan = $user->readingPlans()->find(1);
        $this->assertNull($plan);

        // 6
        $this->get('/reading-plans')
            ->assertDontSee($deleteUrl);
    }

    /**
     * seederからテストデータ生成
     */
    private function createTestDatasFromSeeder(): void {
        $this->seed([
            UserSeeder::class,
            GenreSeeder::class,
            BookSeeder::class,
            ReadingPlanSeeder::class,
        ]);
    }

    /**
     * 不正入力時のメッセージとリダイレクト先を検証
     *
     * @param  string  $from  遷移元URL
     * @param  string  $next  送信先URL
     * @param  string  $action  'POST' or 'PUT'
     * @param  array  $input  リクエスト入力内容
     * @param  array  $errors  期待されるエラー表示先名称と、エラーメッセージ
     */
    private function checkInvalidState(string $from, string $next, string $action, array $input, array $errors): void {
        if ($action === 'PUT') {
            $this->from($from)->put($next, $input)
                ->assertRedirect($from)
                ->assertSessionHasErrors($errors);
        } elseif ($action === 'POST') {
            $this->from($from)->post($next, $input)
                ->assertRedirect($from)
                ->assertSessionHasErrors($errors);
        }

        $this->get($from)
            ->assertOk()
            ->assertSeeTextInOrder(array_values($errors));
    }
}
