<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeleteBookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ゲストは書籍を削除できない
     */
    public function testGuestCannotBooksDelete(): void {
        $book = Book::factory()->create();

        // DB内に登録している書籍数を取得
        $bookCount = Book::count();

        $this->deleteJson('/api/v1/books/' . $book->id)
            ->assertUnauthorized();

        // DB内の書籍数に変更がないか確認
        $this->assertDatabaseCount('books', $bookCount);
    }

    /**
     * 書籍登録者以外のユーザーは書籍を削除できない
     */
    public function testCannotDeleteOthersBook(): void {
        $book = Book::factory()->create();
        $otherUser = User::factory()->create();

        // DB内に登録している書籍数を取得
        $bookCount = Book::count();

        // 書籍登録者でないユーザーが認証し、対象書籍削除のpostを送信し403であることを検証
        Sanctum::actingAs($otherUser, ['*']);
        $this->deleteJson('/api/v1/books/' . $book->id)
            ->assertForbidden();

        // DB内の書籍数に変更がないか確認
        $this->assertDatabaseCount('books', $bookCount);
    }


    /** API書籍詳細を取得し期待通りのレスポンスが返ってくる */
    public function testBooksDelete(): void {
        // 1. 1件の書籍を作成する
        // 2. DELETE /api/v1/books/{book}にアクセス
        // 3. テスト: statusCode(204)が返るか?
        // 4. テスト: DBから削除されているか

        // 1
        $book = Book::factory()->create();
        $book->genres()->sync(Genre::create(['name' => '小説'])->id);
        $this->assertDatabaseCount('books', 1);

        // 2
        Sanctum::actingAs($book->registerUser, ['*']);
        $this->deleteJson('/api/v1/books/' . $book->id)->assertStatus(204);

        // 3
        $this->assertDatabaseEmpty('books');
    }

    /** 存在しない書籍のAPI書籍詳細を実行した時、期待通りのエラーレスポンスが返ってくる */
    public function testBookNotFound(): void {
        // 1. 1件の書籍を作成する
        // 2. DELETE /api/v1/books/2にアクセス
        // 3. テスト: statuscode: 404 {'error' => '書籍情報が見つかりませんでした。'}が返ってくるか

        $book = Book::factory()->create();
        $book->genres()->sync(Genre::create(['name' => '小説'])->id);

        Sanctum::actingAs($book->registerUser, ['*']);
        $this->deleteJson('/api/v1/books/2')
            ->assertStatus(404)
            ->assertJson([
                'error' => '書籍情報が見つかりませんでした。',
            ]);
    }
}
