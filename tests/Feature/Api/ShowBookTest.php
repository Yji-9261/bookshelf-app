<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowBookTest extends TestCase
{
    use RefreshDatabase;

    /** API書籍詳細を取得し期待通りのレスポンスが返ってくる */
    public function testBooksShow(): void
    {
        // 1. 1件の書籍を作成する
        // 2. 作成した書籍に['小説', 'エッセイ']を紐つける
        // 3. 作成した書籍に
        //        rating:1, comment: 'テスト1'
        //        rating:2, comment: 'テスト2'
        //        のレビューを紐つける
        // 4. 作成した書籍を指定して GET /api/v1/books/{book}にアクセス
        // 5. テスト: jsonの出力結果が仕様通りか

        // 1
        $book = Book::factory()->create([
            'image_url' => 'http://placehold.co/200x300/e2e8f0/475569?text=1',
            'description' => 'テスト本',
        ]);

        // 2
        $genres = collect([
            Genre::create(['name' => '小説']),
            Genre::create(['name' => 'エッセイ']),
        ]);
        $book->genres()->sync($genres->pluck('id')->all());

        // 3
        $reviewRatings = collect([1, 2]);
        $reviewers = User::factory()->count(2)->create();
        $reviews = $book->reviews()->createMany([
            ['user_id' => $reviewers[0]->id, 'rating' => $reviewRatings[0], 'comment' => 'テスト1'],
            ['user_id' => $reviewers[1]->id, 'rating' => $reviewRatings[1], 'comment' => 'テスト2'],
        ]);

        // 4,5
        $this->getJson('/api/v1/books/' . $book->id)
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $book->id,
                    'title' => $book->title,
                    'author' => $book->author,
                    'isbn' => $book->isbn,
                    'published_date' => $book->published_date,
                    'image_url' => $book->image_url,
                    'description' => $book->description,
                    'genres' => $genres->map(fn ($genre) => ['id' => $genre->id, 'name' => $genre->name])->all(),
                    'reviews_avg_rating' => $reviewRatings->avg(),
                    'reviews_count' => $reviewRatings->count(),
                    'reviews' => $reviews->map(fn ($review) => [
                        'id' => $review->id,
                        'user_name' => $review->user->name,
                        'rating' => $review->rating,
                        'comment' => $review->comment,
                        'created_at' => $review->created_at->toJSON(),
                    ])->all(),
                ],
            ]);
    }

    /** 存在しない書籍のAPI書籍詳細を実行した時、期待通りのエラーレスポンスが返ってくる */
    public function testBookNotFound(): void
    {
        // 1. 1件の書籍を作成する
        // 2. GET /api/v1/books/2にアクセス
        // 3. テスト: statuscode: 404 {'error' => '書籍情報が見つかりませんでした。'}が返ってくるか

        Book::factory()->create()
            ->genres()
            ->sync(Genre::create(['name' => '小説'])->id);

        $this->getJson('/api/v1/books/2')
            ->assertStatus(404)
            ->assertJson([
                'error' => '書籍情報が見つかりませんでした。',
            ]);
    }
}
