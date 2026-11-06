<?php

namespace Tests\Unit;

use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * registerUserのリレーションが適切か
     */
    public function testRegisterUser(): void {
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
    public function testReviews(): void {
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
    public function testGenres(): void {
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
    public function testFavoriteUsers(): void {
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

    /**
     * ReadingPlanのリレーションが適切か
     */
    public function testReadingPlan(): void {
        /**
         * 1. ユーザー作成
         * 2. $book = $user->registeredBooks()->create([...])で書籍生成
         * 3. テスト:$userと$readinPlanのリレーションが構築できているか
         * 4. テスト:$bookと$readinPlanのリレーションが構築されているか
         * 5. $user->delete()でユーザー削除
         * 6. テスト:$bookと$readinPlanのリレーションが解除できているか
         */

        // 1
        $user = User::factory()->create();

        // 2
        $book = $user->registeredBooks()->create([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000000',
            'published_date' => '2026-10-13',
        ]);
        $genre = Genre::factory()->create(['name' => 'ジャンル']);
        $book->genres()->sync($genre->id);

        // 3
        $readingValue = [
            'user_id' => $user->id,

            // sqliteはdatetimeになるため比較用に時刻も明記しておく
            'target_date' => '2026-01-01 00:00:00',
        ];
        $book->readingPlans()->create($readingValue);

        // 4
        $this->assertTrue($book->readingPlans()->where('user_id', $user->id)->exists());
        $this->assertTrue($user->readingPlans()->where('book_id', $book->id)->exists());
        // DBに登録されていることも確認する
        $this->assertDatabaseHas('reading_plans', $readingValue);

        // 5
        $user->delete();

        // 6
        $this->assertSame(0, $book->readingPlans()->count());
        // DBから削除されていることも確認する
        $this->assertDatabaseMissing('reading_plans', $readingValue);
    }
}
