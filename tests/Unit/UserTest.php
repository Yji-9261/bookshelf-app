<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * パスワードがHash化されているか？
     */
    public function test_hashed_password(): void
    {
        /**
         * 1. User::create(['name'=>'user1', 'email'=>'user1@example.com', 'password'=>'password'])で作成。
         * 2. Test : Usersテーブルに登録されているpasswordがハッシュ化されているか検証
         */

        $user = User::create([
            'name' => 'user1',
            'email' => 'user1@example.com',
            'password' => 'password'
        ]);

        // DBから再読み込み
        $user->refresh();

        // DBにパスワードがHash化されて登録されているか？
        $this->assertTrue(Hash::check('password', $user->password));
    }

    /**
     * registeredBooksのリレーションが適切か
     */
    public function test_registeredBooks(): void
    {
        /**
         * 1. $user = User::create([...])で作成
         * 2. $book = $user->registeredBooks()->create([...])で書籍作成
         * 3. テスト:$userと$bookが紐つけられているか
         *    $user->registeredBooks()->whereKey($book->id)->exists()がtrue
         *    booksテーブルに'id' => $book->id, 'user_id' => $user->idの行が存在する
         * 4. $book->delete()で書籍削除
         * 5. テスト:$userと$bookのリレーションが解除されているか
         *    $user->registeredBooks()->whereKey($book->id)->exists()がfalse
         *    booksテーブルに'id' => $book->id, 'user_id' => $user->idの行が存在しない
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
        $this->assertTrue($user->registeredBooks()->whereKey($book->id)->exists());
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $user->id,
        ]);

        // 4
        $book->delete();

        // 5
        $this->assertFalse($user->registeredBooks()->whereKey($book->id)->exists());
        $this->assertDatabaseMissing('books', [
            'id' => $book->id,
            'user_id' => $user->id,
        ]);
    }

    /**
     * postReviewsのリレーションが適切か
     */
    public function test_postReviews(): void
    {
        /**
         * 1. $user = User::create([...])で作成
         * 2. $book = $user->registeredBooks()->create([...])で書籍作成
         * 3. $review = $user->postReviews()->create(['book_id' => $book->id, 'rating' => 5, 'comment' => '読みやすい本でした'])でレビュー作成
         *    user_idはpostReviews()によって自動設定される
         * 4. テスト:$userと$reviewが紐つけられているか
         *    $user->postReviews()->whereKey($review->id)->exists()がtrue
         *    reviewsテーブルに'id' => $review->id, 'user_id' => $user->id, 'book_id' => $book->idの行が存在する
         * 5. $review->delete()でレビュー削除
         * 6. テスト:$userと$reviewのリレーションが解除されているか
         *    $user->postReviews()->whereKey($review->id)->exists()がfalse
         *    reviewsテーブルに'id' => $review->id, 'user_id' => $user->id, 'book_id' => $book->idの行が存在しない
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
        $review = $user->postReviews()->create([
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '読みやすい本でした',
        ]);

        // 4
        $this->assertTrue($user->postReviews()->whereKey($review->id)->exists());
        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);

        // 5
        $review->delete();

        // 6
        $this->assertFalse($user->postReviews()->whereKey($review->id)->exists());
        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
            'user_id' => $user->id,
            'book_id' => $book->id,
        ]);
    }

    /**
     * likedReviewsのリレーションが適切か
     */
    public function test_likedReviews(): void
    {
        /**
         * 1. $user = User::create([...])で作成
         * 2. $book = $user->registeredBooks()->create([...])で書籍生成
         * 3. $review = $book->reviews()->create(['user_id' => $user,...])レビュー作成
         * 4. $user->likedReviews()->syncWithoutDetaching($review->id)でリレーション構築
         * 5. テスト:$userと$reviewのリレーションが構築できているか
         *    $user->likedReviews()->whereKey($review->id)->exists()がtrue
         *    review_likesテーブル内の'user_id'=>$user->idと'review_id'=>$review->idが存在する 
         * 6. $user->likedReviews()->detach($review->id)を使用してリレーション解除
         * 7. テスト:$userと$reviewのリレーションが解除されているか
         *    $user->likedReviews()->whereKey($review->id)->exists()がfalse
         *    review_likesテーブル内の'user_id'=>$user->idと'review_id'=>$review->idが存在しない
         * 8. $user->likedReviews()->syncWithoutDetaching($review->id)でリレーション構築
         * 9. $review->deleteレビュー削除
         * 10. テスト:userとreviewの紐つけが解除されているか
         *    $user->likedReviews()->whereKey($review->id)->exists()がfalse
         *    review_likesテーブル内の'user_id'=>$user->idと'review_id'=>$review->idが存在しない
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

        $like = [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ];

        // 4
        $user->likedReviews()->syncWithoutDetaching($review->id);

        // 5
        $this->assertTrue($user->likedReviews()->whereKey($review->id)->exists());
        $this->assertDatabaseHas('review_likes', $like);

        // 6
        $user->likedReviews()->detach($review->id);

        // 7
        $this->assertFalse($user->likedReviews()->whereKey($review->id)->exists());
        $this->assertDatabaseMissing('review_likes', $like);

        // 8(念のためリレーションが構築されているかテスト)
        $user->likedReviews()->syncWithoutDetaching($review->id);
        $this->assertDatabaseHas('review_likes', $like);

        // 9
        $review->delete();

        // 10
        $this->assertFalse($user->likedReviews()->whereKey($review->id)->exists());
        $this->assertDatabaseMissing('review_likes', $like);
    }


    /**
     * favoriteBooksのリレーションが適切か
     */
    public function test_favoriteBooks(): void
    {
        /**
         * 1. $user = User::create([...])で作成
         * 2. $book = $user->registeredBooks()->create([...])で書籍生成
         * 3. $user->favoriteBooks()->syncWithoutDetaching($book->id)でリレーション構築
         * 4. テスト:$userと$bookがリレーションが構築できているか
         *    $user->favoriteBooks()->whereKey($book->id)->exists()がtrue
         *    favoritesテーブル内の'user_id'=>$user->idと'book_id'=>$book->idが存在する 
         * 5. $user->favoriteBooks()->detach($book->id)を使用してリレーション解除
         * 6. テスト:$userと$bookのリレーションが解除されているか
         *    $user->favoriteBooks()->whereKey($book->id)->exists()がfalse
         *    favoritesテーブル内の'user_id'=>$user->idと'book_id'=>$book->idが存在しない
         * 7. $user->favoriteBooks()->syncWithoutDetaching($book->id)でリレーション構築
         * 8. $book->deleteで削除
         * 9. テスト:$userと$bookのリレーションが解除されているか
         *    $user->favoriteBooks()->whereKey($book->id)->exists()がfalse
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
        $user->favoriteBooks()->syncWithoutDetaching($book->id);

        // 4
        $this->assertTrue($user->favoriteBooks()->whereKey($book->id)->exists());
        $this->assertDatabaseHas('favorites', $favorite);

        // 5
        $user->favoriteBooks()->detach($book->id);

        // 6
        $this->assertFalse($user->favoriteBooks()->whereKey($book->id)->exists());
        $this->assertDatabaseMissing('favorites', $favorite);

        // 7(念のためリレーション構築されているかテスト)
        $user->favoriteBooks()->syncWithoutDetaching($book->id);
        $this->assertDatabaseHas('favorites', $favorite);

        // 8
        $book->delete();

        // 9
        $this->assertFalse($user->favoriteBooks()->whereKey($book->id)->exists());
        $this->assertDatabaseMissing('favorites', $favorite);
    }
}
