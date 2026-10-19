<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 認証ユーザーが登録していない書籍の編集画面にアクセスする
     */
    public function testCannotEditOthersBook(): void
    {
        // 1. GET /books/{book}/edit パスパラメータに認証ユーザー以外が登録した書籍を指定する
        // 2. テスト: statusCode(403)でThis action is unauthorized.が表示される

        // 1
        $createdUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $createdUser->id]);
        $book->genres()->sync(Genre::createOrFirst(['name' => '小説'])->id);

        // 2
        $loginUser = User::factory()->create();
        $response = $this->actingAs($loginUser)
            ->get("/books/{$book->id}/edit");

        $response->assertStatus(403)->assertSee('This action is unauthorized.');
    }

    /**
     * 認証ユーザーが投稿していないレビューの編集画面にアクセスする
     */
    public function testCannotEditOthersReview(): void
    {
        // 1. GET /reviews/{review}/editのパスパラメータに認証ユーザー以外が投稿したレビューを指定する
        // 2. テスト: statusCode(403)でThis action is unauthorized.が表示される

        // 1
        $createdUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $createdUser->id]);
        $book->genres()->sync(Genre::createOrFirst(['name' => '小説'])->id);
        $review = $book->reviews()->create([
            'user_id' => $createdUser->id,
            'rating' => 1,
            'comment' => 'テスト'
        ]);

        // 2
        $loginUser = User::factory()->create();
        $response = $this->actingAs($loginUser)
            ->get("/reviews/{$review->id}/edit");

        $response->assertStatus(403)->assertSee('This action is unauthorized.');
    }

    /**
     * 本人が登録していない書籍の「更新」を実行する
     * この処理はブラウザ上ではできないようになっているため、外部からリクエストされた体で行う
     */
    public function testCannotUpdateOthersBook(): void
    {
        // 1. PUT /books/{$book}のパスパラメータに認証ユーザー以外が登録した書籍を指定する
        // 2. テスト: 更新に失敗する

        // 1
        $createdUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $createdUser->id]);
        $book->genres()->sync(Genre::createOrFirst(['name' => '小説'])->id);
        $original = $book->refresh()->getAttributes();

        // 2
        $loginUser = User::factory()->create();
        $this->actingAs($loginUser)
            ->put("/books/{$book->id}", [
                'title' => '更新後のテスト本',
                'author' => '更新後の著者',
                'isbn' => '9780000000000',
                'published_date' => '2026-01-01',
                'image_url' => 'https://example.com/updated.jpg',
                'description' => '更新後の書籍説明です。',
                'genres' => $book->genres()->get()->map(fn($genre) => $genre->id)->toArray()
            ])
            ->assertStatus(403);

        // DB内容がPUT送信前と変更がないか確認
        $this->assertSame($original, $book->refresh()->getAttributes());
    }

    /**
     * 本人が登録していない書籍の「削除」を実行する
     * この処理はブラウザ上ではできないようになっているため、外部からリクエストされた体で行う
     */
    public function testCannotDeleteOthersBook(): void
    {
        // 1. DELETE /books/{$book}のパスパラメータに認証ユーザー以外が登録した書籍を指定する
        // 2. テスト: 削除に失敗する

        // 1
        $createdUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $createdUser->id]);
        $book->genres()->sync(Genre::createOrFirst(['name' => '小説'])->id);
        $original = $book->refresh()->getAttributes();

        // 2
        $loginUser = User::factory()->create();
        $this->actingAs($loginUser)
            ->delete("/books/{$book->id}")
            ->assertStatus(403);

        // DB内容がDELETE送信前と変更がないか確認
        $this->assertSame($original, $book->refresh()->getAttributes());
    }

    /**
     * 本人が登録していないレビューの「更新」を実行する
     * この処理はブラウザ上ではできないようになっているため、外部からリクエストされた体で行う
     */
    public function testCannotUpdateOthersReview(): void
    {
        // 1. PUT /reviews/{review}のパスパラメータに認証ユーザー以外が投稿したレビューを指定する
        // 2. テスト: 更新に失敗する

        // 1
        $createdUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $createdUser->id]);
        $book->genres()->sync(Genre::createOrFirst(['name' => '小説'])->id);
        $review = $book->reviews()->create([
            'user_id' => $createdUser->id,
            'rating' => 1,
            'comment' => 'テスト'
        ]);
        $original = $review->refresh()->getAttributes();

        // 2
        $loginUser = User::factory()->create();
        $this->actingAs($loginUser)
            ->put("/reviews/{$review->id}", [
                'rating' => 5,
                'comment' => '更新後のテスト'
            ])
            ->assertStatus(403);

        // DB内容がPUT送信前と変更がないか確認
        $this->assertSame($original, $review->refresh()->getAttributes());
    }

    /**
     * 本人が登録していないレビューの「削除」を実行する
     * この処理はブラウザ上ではできないようになっているため、外部からリクエストされた体で行う
     */
    public function testCannotDeleteOthersReview(): void
    {
        // 1. DELETE /reviews/{review}のパスパラメータに認証ユーザー以外が投稿したレビューを指定する
        // 2. テスト: 更新に失敗する

        // 1
        $createdUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $createdUser->id]);
        $book->genres()->sync(Genre::createOrFirst(['name' => '小説'])->id);
        $review = $book->reviews()->create([
            'user_id' => $createdUser->id,
            'rating' => 1,
            'comment' => 'テスト'
        ]);
        $original = $review->refresh()->getAttributes();

        // 2
        $loginUser = User::factory()->create();
        $this->actingAs($loginUser)
            ->delete("/reviews/{$review->id}")
            ->assertStatus(403);

        // DB内容がDELETE送信前と変更がないか確認
        $this->assertSame($original, $review->refresh()->getAttributes());
    }

    /**
     * ゲストが書籍の「新規登録」をする
     * この処理はブラウザ上ではできないようになっているため、外部からリクエストされた体で行う
     */
    public function testCannotCreateBookByGuest(): void
    {
        // 1. ゲストとして POST /books/create に正常なリクエストを送信する
        // 2. テスト: 書籍の登録が拒否される

        // 1
        $this->assertDatabaseEmpty('books');

        // 2 auth middlewareにてloginリダイレクト
        $this->post("/books/create", [
            'title' => 'テスト',
            'author' => 'テスト',
            'isbn' => '9780000000000',
            'published_date' => '2026-01-01',
            'genres' => [Genre::createOrFirst(['name' => '小説'])->id],
        ])->assertRedirect('/login');

        // DBへの登録も失敗する
        $this->assertDatabaseEmpty('books');
    }

    /**
     * ゲストがレビューの「新規登録」をする
     * この処理はブラウザ上ではできないようになっているため、外部からリクエストされた体で行う
     */
    public function testCannotCreateReviewByGuest(): void
    {
        // 1. ゲストとして POST /books/{book}/reviews に正常なリクエストを送信する
        // 2. テスト: レビューの投稿が拒否される

        $createdUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $createdUser->id]);
        $book->genres()->sync(Genre::createOrFirst(['name' => '小説'])->id);

        // 1
        $this->assertDatabaseEmpty('reviews');

        // 2 auth middlewareにてloginリダイレクト
        $this->post("/books/{$book->id}/reviews", [
            'rating' => 1,
            'comment' => 'テスト',
        ])->assertRedirect('/login');

        // DBへの登録も失敗する
        $this->assertDatabaseEmpty('reviews');
    }

    /**
     * ゲストがジャンルの「新規登録」をする
     * この処理はブラウザ上ではできないようになっているため、外部からリクエストされた体で行う
     */
    public function testCannotCreateGenreByGuest(): void
    {
        // 1. ゲストとして POST /genres に正常なリクエストを送信する
        // 2. テスト: ジャンルの登録が拒否される

        // 1
        $this->assertDatabaseEmpty('genres');

        // 2 auth middlewareにてloginリダイレクト
        $this->post("/genres", [
            'name' => 'テスト',
        ])->assertRedirect('/login');

        // DBへの登録も失敗する
        $this->assertDatabaseEmpty('genres');
    }

    /**
     * ゲストがいいねの「登録」をする
     * この処理はブラウザ上ではできないようになっているため、外部からリクエストされた体で行う
     */
    public function testCannotCreateReviewLikesByGuest(): void
    {
        // 1. レビュー作成する
        // 2. ゲストとしてPOST /reviews/{review}/likeに正常なリクエストでアクセスする
        // 3. テスト: いいねの登録が拒否される

        // 1
        $createdUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $createdUser->id]);
        $book->genres()->sync(Genre::createOrFirst(['name' => '小説'])->id);
        $review = $book->reviews()->create([
            'user_id' => $createdUser->id,
            'rating' => 1,
            'comment' => 'テスト'
        ]);

        $this->assertDatabaseEmpty('review_likes');

        // 2,3 auth middlewareにてloginリダイレクト
        $this->post("/reviews/{$review->id}/like")->assertRedirect('/login');
        $this->assertDatabaseEmpty('review_likes');
    }

    /**
     * ゲストがお気に入りの「登録」をする
     * この処理はブラウザ上ではできないようになっているため、外部からリクエストされた体で行う
     */
    public function testCannotCreateFavoriteByGuest(): void
    {
        // 1. 書籍を作成する
        // 2. ゲストとしてPOST /books/{book}/favoritesに正常なリクエストでアクセスする
        // 3. テスト: お気に入りの登録が拒否される
        $createdUser = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $createdUser->id]);
        $book->genres()->sync(Genre::createOrFirst(['name' => '小説'])->id);

        $this->assertDatabaseEmpty('favorites');

        // 2,3 auth middlewareにてloginリダイレクト
        $this->post("/book/{$book->id}/favorites")->assertRedirect('/login');
        $this->assertDatabaseEmpty('favorites');
    }
}
