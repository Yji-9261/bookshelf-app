<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Genre;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use function PHPUnit\Framework\assertSame;

class BookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * reviewsAvgRatingアクセサテスト
     */
    public function test_reviews_avg_rating(): void
    {
        /**
         * 1. テスト用レビューデータ作成
         * 2. テスト : reviews_avg_ratingが想定通りの結果になる
         */

        // 1
        $user = User::create([
            'name' => 'user1',
            'email' => 'user1@example.com',
            'password' => 'password'
        ]);
        $book = $user->registeredBooks()->create([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000000',
            'published_date' => '2026-10-13',
        ]);
        $testReviews = [
            [
                'user_id' => $user->id,
                'rating' => 5,
                'comment' => '',
            ],
            [
                'user_id' => $user->id,
                'rating' => 4,
                'comment' => '',
            ],
            [
                'user_id' => $user->id,
                'rating' => 3,
                'comment' => '',
            ],
        ];
        $book->reviews()->createMany($testReviews);

        // 2
        $this->assertSame($book->reviews_avg_rating, (float) (5 + 4 + 3) / 3);
    }


    /**
     * registerUserのリレーションが適切か
     * 
     * @return void
     */
    public function test_registerUser(): void
    {
        /**
         * 1. $user = User::create([...])で作成
         * 2. $book = $user->registeredBooks()->create([...])で書籍作成
         * 3. テスト:$userと$bookが紐つけられているか
         *    $book->registerUser()->whereKey($user->id)->exists()がtrue
         *    booksテーブルに'id' => $book->id, 'user_id' => $user->idの行が存在する
         */
        $user = User::create([
            'name' => 'user1',
            'email' => 'user1@example.com',
            'password' => 'password',
        ]);

        $book = $user->registeredBooks()->create([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000000',
            'published_date' => '2026-10-13',
        ]);

        $this->assertTrue($book->registerUser()->whereKey($user->id)->exists());
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * reviewsのリレーションが適切か
     */
    public function test_reviews(): void
    {
        /**
         * 1. $user = User::create([...])で作成
         * 2. $book = $user->registeredBooks()->create([...])で書籍作成
         * 3. $review = $book->reviews()->create([...])でレビュー作成
         *    user_id、rating、commentを指定し、book_idはreviews()によって自動設定される
         * 4. テスト:$bookと$reviewが紐つけられているか
         *    $book->reviews()->whereKey($review->id)->exists()がtrue
         *    reviewsテーブルに'id' => $review->id, 'book_id' => $book->idの行が存在する
         * 5. $review->delete()でレビュー削除
         * 6. テスト:$bookと$reviewのリレーションが解除されているか
         *    $book->reviews()->whereKey($review->id)->exists()がfalse
         *    reviewsテーブルに'id' => $review->id, 'book_id' => $book->idの行が存在しない
         */

        // 1
        $user = User::create([
            'name' => 'user1',
            'email' => 'user1@example.com',
            'password' => 'password',
        ]);

        // 2
        $book = $user->registeredBooks()->create([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000000',
            'published_date' => '2026-10-13',
        ]);

        // 3
        $review = $book->reviews()->create([
            'user_id' => $user->id,
            'rating' => 5,
            'comment' => '読みやすい本でした',
        ]);

        // 4
        $this->assertTrue($book->reviews()->whereKey($review->id)->exists());
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'book_id' => $book->id,
        ]);

        // 5
        $review->delete();

        // 6
        $this->assertFalse($book->reviews()->whereKey($review->id)->exists());
        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * genresのリレーションが適切か
     */
    public function test_genres(): void
    {
        /**
         * 1. $user = User::create([...])で作成
         * 2. $book = $user->registeredBooks()->create([...])で書籍作成
         * 3. $genre = Genre::create(['name' => 'テスト'])でジャンル作成
         * 4. $book->genres()->syncWithoutDetaching($genre->id)でリレーション構築
         * 5. テスト:$bookと$genreが紐つけられているか
         *    $book->genres()->whereKey($genre->id)->exists()がtrue
         *    book_genreテーブルに'book_id' => $book->id, 'genre_id' => $genre->idの行が存在する
         * 6. $book->genres()->detach($genre->id)を使用してリレーション解除
         * 7. テスト:$bookと$genreのリレーションが解除されているか
         *    $book->genres()->whereKey($genre->id)->exists()がfalse
         *    book_genreテーブルに'book_id' => $book->id, 'genre_id' => $genre->idの行が存在しない
         * 8. $book->genres()->syncWithoutDetaching($genre->id)でリレーション構築
         * 9. $genre->delete()でジャンル削除
         * 10. テスト:$bookと$genreのリレーションが解除されているか
         *    $book->genres()->whereKey($genre->id)->exists()がfalse
         *    book_genreテーブルに'book_id' => $book->id, 'genre_id' => $genre->idの行が存在しない
         */

        // 1
        $user = User::create([
            'name' => 'user1',
            'email' => 'user1@example.com',
            'password' => 'password',
        ]);

        // 2
        $book = $user->registeredBooks()->create([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000000',
            'published_date' => '2026-10-13',
        ]);

        // 3
        $genre = Genre::create(['name' => 'テスト']);
        $bookGenre = [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ];

        // 4
        $book->genres()->syncWithoutDetaching($genre->id);

        // 5
        $this->assertTrue($book->genres()->whereKey($genre->id)->exists());
        $this->assertDatabaseHas('book_genre', $bookGenre);

        // 6
        $book->genres()->detach($genre->id);

        // 7
        $this->assertFalse($book->genres()->whereKey($genre->id)->exists());
        $this->assertDatabaseMissing('book_genre', $bookGenre);

        // 8
        $book->genres()->syncWithoutDetaching($genre->id);
        $this->assertDatabaseHas('book_genre', $bookGenre);

        // 9
        $genre->delete();

        // 10
        $this->assertFalse($book->genres()->whereKey($genre->id)->exists());
        $this->assertDatabaseMissing('book_genre', $bookGenre);
    }

    /**
     * favoriteUsersのリレーションが適切か
     */
    public function test_favoriteUsers(): void
    {
        /**
         * 1. $user = User::create([...])で生成
         * 2. $book = $user->registeredBooks()->create([...])で書籍生成
         * 3. $book->favoriteUsers()->syncWithoutDetaching($user->id)でリレーション構築
         * 4. テスト:$userと$bookのリレーションが構築できているか
         *    $book->favoriteUsers()->whereKey($user->id)->exists()がtrue
         *    favoritesテーブル内の'user_id'=>$user->idと'book_id'=>$book->idが存在する 
         * 5. $book->favoriteUsers()->detach($user->id)を使用してリレーション解除
         * 6. テスト:$userと$bookのリレーションが解除されているか
         *    $book->favoriteUsers()->whereKey($user->id)->exists()がfalse
         *    favoritesテーブル内の'user_id'=>$user->idと'book_id'=>$book->idが存在しない
         * 7. $book->favoriteUsers()->syncWithoutDetaching($user->id)でリレーション構築
         * 8. $book->delete()で書籍削除
         * 9. テスト:userとbookの紐つけが解除されているか
         *    $book->favoriteUsers()->whereKey($user->id)->exists()がfalse
         *    favoritesテーブル内の'user_id'=>$user->idと'book_id'=>$book->idが存在しない
         */

        // 1
        $user = User::create([
            'name' => 'user1',
            'email' => 'user1@example.com',
            'password' => 'password',
        ]);

        // 2
        $book = $user->registeredBooks()->create([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000000',
            'published_date' => '2026-10-13',
        ]);

        $favorite = [
            'user_id' => $user->id,
            'book_id' => $book->id,
        ];

        // 3
        $book->favoriteUsers()->syncWithoutDetaching($user->id);

        // 4
        $this->assertTrue($book->favoriteUsers()->whereKey($user->id)->exists());
        $this->assertDatabaseHas('favorites', $favorite);

        // 5
        $book->favoriteUsers()->detach($user->id);

        // 6
        $this->assertFalse($book->favoriteUsers()->whereKey($user->id)->exists());
        $this->assertDatabaseMissing('favorites', $favorite);

        // 7
        $book->favoriteUsers()->syncWithoutDetaching($user->id);
        $this->assertDatabaseHas('favorites', $favorite);

        // 8
        $book->delete();

        // 9
        $this->assertFalse($book->favoriteUsers()->whereKey($user->id)->exists());
        $this->assertDatabaseMissing('favorites', $favorite);
    }
}
