<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Review;
use Database\Seeders\UserSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\ReviewSeeder;
use Database\Seeders\FavoriteSeeder;
use Database\Seeders\ReviewLikeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditReviewTest extends TestCase
{
    use RefreshDatabase;

    public const RATING = 5;
    public const COMMENT = 'テストレビューです';

    /**
     * PUT送信処理
     * @param Review $review
     * @param mixed $rating
     * @param mixed $comment
     * @return \Illuminate\Testing\TestResponse
     */
    private function putUpdateReview(
        Review $review,
        $rating = self::RATING,
        $comment = self::COMMENT,
    ) {
        return $this->actingAs(User::findOrFail($review->user_id))
            ->put(route('reviews.update', $review), [
                'rating' => $rating,
                'comment' => $comment,
            ]);
    }

    /** ゲストとしてレビュー編集画面へアクセスする */
    public function testCannotGuestEditReview(): void
    {
        // 1. レビューを作成する
        // 2. ゲストとしてGET /reviews/{review}/editにアクセス
        // 3. テスト: ログイン画面にリダイレクトする

        $review = Review::factory()->create();
        $this->get("/reviews/{$review->id}/edit")->assertRedirect('/login');
    }

    /** レビュー編集画面が表示される */
    public function testEditView(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. 対象レビュー投稿者として認証し、レビュー編集画面にアクセス
        // 3. テスト: 既存のrating/commentが初期値として表示された編集フォームが表示される

        // 1
        $this->createTestDataFromSeeder();
        $review = Review::firstOrFail();

        // 2
        $response = $this->actingAs(User::findOrFail($review->user_id))
            ->get(route('reviews.edit', $review))
            ->assertOk()
            ->assertViewIs('reviews.edit')
            ->assertViewHas('review', fn(Review $shownReview) => $shownReview->is($review));

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@action="' . route('reviews.update', $review) . '"]');
        $this->assertCount(1, $forms);
        $form = $forms->item(0);

        $this->assertCount(5, $xpath->query('.//input[@type="radio" and @name="rating"]', $form));
        for ($rating = 1; $rating <= 5; $rating++) {
            $inputs = $xpath->query('.//input[@type="radio" and @name="rating" and @value="' . $rating . '"]', $form);
            $this->assertCount(1, $inputs);
            $this->assertSame((int) $review->rating === $rating, $inputs->item(0)->hasAttribute('checked'));
        }

        $comments = $xpath->query('.//textarea[@name="comment"]', $form);
        $this->assertCount(1, $comments);
        $this->assertSame($review->comment, $comments->item(0)->textContent);
    }

    /** 正常な入力をして「更新」ボタンを押下する */
    public function testUpdateReview(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. レビュー投稿者として認証し、putUpdateReview()でレビュー更新
        // 3. テスト: 書籍詳細画面にリダイレクトされる
        // 4. テスト: DBの内容が更新されている
        // 5. テスト: 書籍詳細画面にレビューの編集内容が反映されている

        // 1
        $this->createTestDataFromSeeder();
        $review = Review::firstOrFail();

        // 検証データ
        $reviewCount = Review::count();
        $userId = $review->user_id;
        $bookId = $review->book_id;
        $rating = (int) $review->rating === 5 ? 4 : 5;
        $comment = '更新したレビューです。以前とは異なる感想です。';
        $showUrl = route('books.show', $review->book);

        // 2(エラーなし、フラッシュメッセージも検証)
        $this->putUpdateReview(review: $review, rating: $rating, comment: $comment)
            ->assertSessionHasNoErrors()
            ->assertRedirect($showUrl)
            ->assertSessionHas('success', 'レビューを更新しました。');

        // 4
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $userId,
            'book_id' => $bookId,
            'rating' => $rating,
            'comment' => $comment,
        ]);
        // 編集のため増えてはいないことを確認
        $this->assertDatabaseCount('reviews', $reviewCount);

        // 5
        $response = $this->get($showUrl)
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertSeeText('レビューを更新しました。')
            ->assertSeeText($comment);

        // 対象レビューが更新後の評価・コメントでビューに渡されていることを確認
        $shownReview = $response->viewData('book')->reviews->find($review->id);
        $this->assertNotNull($shownReview);
        $this->assertSame($rating, (int) $shownReview->rating);
        $this->assertSame($comment, $shownReview->comment);
        $response->assertSeeText(str_repeat('★', $rating) . str_repeat('☆', 5 - $rating));
    }

    /**
     * 不正項目の入力に対して、バリデーションメッセージが表示される(rating編)
     * このテストは、ブラウザ操作ではなく、外部からput送信された時に書き換わることがないかのテスト
     */
    public function testRatingValidation(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. レビュー投稿者として認証し、putUpdateReview(rating: null)でレビュー更新
        // 3. テスト: 更新に失敗し、DBに変化がない
        // 4. putUpdateReview(rating: '1.5')でレビュー更新（文字列'1'は有効なため、小数を使う）
        // 5. テスト: 更新に失敗し、DBに変化がない
        // 6. putUpdateReview(rating: 0)でレビュー更新
        // 7. テスト: 更新に失敗し、DBに変化がない
        // 8. putUpdateReview(rating: 6)でレビュー更新
        // 9. テスト: 更新に失敗し、DBに変化がない

        // 1
        $this->createTestDataFromSeeder();
        $review = Review::firstOrFail();

        // 検証データ
        $original = $review->getAttributes();
        $reviewCount = Review::count();
        $editUrl = route('reviews.edit', $review);
        $cases = [
            [null, '評価を入力してください'],
            ['1.5', '評価は数値を入力してください'],
            [0, '評価は１以上の数値を入力してください'],
            [6, '評価は５以下の数値を入力してください'],
        ];

        // 2~9
        foreach ($cases as [$rating, $message]) {
            $this->from($editUrl)->putUpdateReview(review: $review, rating: $rating)
                ->assertRedirect($editUrl)
                ->assertSessionHasErrors(['rating' => $message])
                ->assertSessionDoesntHaveErrors('comment');

            $this->assertSame($original, $review->fresh()->getAttributes());
            $this->assertDatabaseCount('reviews', $reviewCount);
        }
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(comment編) */
    public function testCommentValidation(): void
    {
        // 1. テストデータを作成する
        // 2. レビュー投稿者として認証し、commentを空にしてPUT送信
        // 3. テスト: 編集画面に戻り、「コメントを入力してください」が表示され、DBの内容が更新されていない

        // 1
        $this->createTestDataFromSeeder();
        $review = Review::firstOrFail();

        // 検証データ
        $original = $review->getAttributes();
        $reviewCount = Review::count();
        $editUrl = route('reviews.edit', $review);

        // 2
        $this->from($editUrl)->putUpdateReview(review: $review, comment: '')
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors(['comment' => 'コメントを入力してください'])
            ->assertSessionDoesntHaveErrors('rating');

        // 3
        $this->get($editUrl)
            ->assertOk()
            ->assertViewIs('reviews.edit')
            ->assertSeeText('コメントを入力してください');

        $this->assertSame($original, $review->fresh()->getAttributes());
        $this->assertDatabaseCount('reviews', $reviewCount);
    }

    /**
     * seederからテストデータ生成
     */
    private function createTestDataFromSeeder(): void
    {
        // シーダーからテストデータを作成する
        $this->seed([
            UserSeeder::class,
            GenreSeeder::class,
            BookSeeder::class,
            ReviewSeeder::class,
            FavoriteSeeder::class,
            ReviewLikeSeeder::class,

        ]);
    }
}
