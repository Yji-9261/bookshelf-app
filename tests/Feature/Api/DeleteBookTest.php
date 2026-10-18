<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Database\Seeders\UserSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\ReviewSeeder;
use Database\Seeders\FavoriteSeeder;
use Database\Seeders\ReviewLikeSeeder;


use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class DeleteBookTest extends TestCase
{
    use RefreshDatabase;

    /** API書籍詳細を取得し期待通りのレスポンスが返ってくる */
    public function testBooksDelete(): void
    {
        // 1. 1件の書籍を作成する
        // 2. DELETE /api/v1/books/{book}にアクセス
        // 3. テスト: statusCode(204)が返るか?
        // 4. テスト: DBから削除されているか

        // 1
        $book = Book::factory()->create();
        $book->genres()->sync(Genre::create(['name' => '小説'])->id);
        $this->assertDatabaseCount('books', 1);

        // 2
        $this->deleteJson('/api/v1/books/' . $book->id)->assertStatus(204);

        // 3
        $this->assertDatabaseEmpty('books');

    }

    /** 存在しない書籍のAPI書籍詳細を実行した時、期待通りのエラーレスポンスが返ってくる */
    public function testBookNotFound(): void
    {
        // 1. 1件の書籍を作成する
        // 2. DELETE /api/v1/books/2にアクセス
        // 3. テスト: statuscode: 404 {'error' => '書籍情報が見つかりませんでした。'}が返ってくるか

        Book::factory()->create()->genres()->sync(Genre::create(['name' => '小説'])->id);

        $this->getJson('/api/v1/books/2')
            ->assertStatus(404)
            ->assertJson([
                'error' => '書籍情報が見つかりませんでした。',
            ]);
    }
}
