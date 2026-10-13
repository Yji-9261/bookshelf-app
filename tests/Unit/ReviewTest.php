<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * userのリレーションが適切か
     */
    public function test_user(): void
    {
        /**
         * 1. $user = User::create([...])で作成
         * 2. $book = $user->registeredBooks()->create([...])で書籍作成
         * 3. $review = $user->postReviews()->create(['book_id' => $book->id, 'rating' => 5, 'comment' => '読みやすい本でした'])でレビュー作成
         * 4. テスト:$userと$reviewが紐つけられているか
         *    $review->user()->whereKey($user->id)->exists()がtrue
         * ※ DBテーブルの保存検証はUserTestで行っているため省略
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

        $review = $user->postReviews()->create([
            'book_id' => $book->id,
            'rating' => 5,
            'comment' => '読みやすい本でした',
        ]);

        $this->assertTrue($review->user()->whereKey($user->id)->exists());
    }

    /**
     * bookのリレーションが適切か
     */
    public function test_book(): void
    {
        /**
         * 1. $user = User::create([...])で作成
         * 2. $book = $user->registeredBooks()->create([...])で書籍作成
         * 3. $review = $user->postReviews()->create(['book_id' => $book->id, 'rating' => 5, 'comment' => '読みやすい本でした'])でレビュー作成
         * 4. テスト:$bookと$reviewが紐つけられているか
         *    $review->book()->whereKey($book->id)->exists()がtrue
         * ※ DBテーブルの保存検証はBookTestで行っているため省略
         * 5. $book->delete()で削除
         * 6. テスト:$bookと$reviewのリレーションが解除されているか
         *    $review->book()->whereKey($book->id)->exists()がfalse
         * ※ DBテーブルからの削除検証はBookTestで行っているため省略
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
        $this->assertTrue($review->book()->whereKey($book->id)->exists());

        // 5
        $book->delete();

        // 6
        $this->assertFalse($review->book()->whereKey($book->id)->exists());
    }

    /**
     * likedByUsersのリレーションが適切か
     */
    public function test_likedByUsers(): void
    {
        /**
         * 1. 投稿者$authorと、いいねする$userをUser::create([...])で作成
         * 2. $book = $author->registeredBooks()->create([...])で書籍生成
         * 3. $review = $book->reviews()->create(['user_id' => $author->id, ...])でレビュー作成
         * 4. $review->likedByUsers()->syncWithoutDetaching($user->id)でリレーション構築
         * 5. テスト:$userと$reviewのリレーションが構築できているか
         *    $review->likedByUsers()->whereKey($user->id)->exists()がtrue
         *    review_likesテーブル内の'user_id'=>$user->idと'review_id'=>$review->idが存在する 
         * 6. $review->likedByUsers()->detach($user->id)を使用してリレーション解除
         * 7. テスト:$userと$reviewのリレーションが解除されているか
         *    $review->likedByUsers()->whereKey($user->id)->exists()がfalse
         *    review_likesテーブル内の'user_id'=>$user->idと'review_id'=>$review->idが存在しない
         * 8. $review->likedByUsers()->syncWithoutDetaching($user->id)でリレーション構築
         * 9. $user->delete()でいいねしたユーザーを削除
         * 10. テスト:userとreviewの紐つけが解除されているか
         *    $review->likedByUsers()->whereKey($user->id)->exists()がfalse
         *    review_likesテーブル内の'user_id'=>$user->idと'review_id'=>$review->idが存在しない
         */

        // 1
        $author = User::create([
            'name' => 'author',
            'email' => 'author@example.com',
            'password' => 'password',
        ]);

        $user = User::create([
            'name' => 'user1',
            'email' => 'user1@example.com',
            'password' => 'password',
        ]);

        // 2
        $book = $author->registeredBooks()->create([
            'title' => 'テスト書籍',
            'author' => 'テスト著者',
            'isbn' => '9784000000000',
            'published_date' => '2026-10-13',
        ]);

        // 3
        $review = $book->reviews()->create([
            'user_id' => $author->id,
            'rating' => 5,
            'comment' => '読みやすい本でした',
        ]);

        $like = [
            'user_id' => $user->id,
            'review_id' => $review->id,
        ];

        // 4
        $review->likedByUsers()->syncWithoutDetaching($user->id);

        // 5
        $this->assertTrue($review->likedByUsers()->whereKey($user->id)->exists());
        $this->assertDatabaseHas('review_likes', $like);

        // 6
        $review->likedByUsers()->detach($user->id);

        // 7
        $this->assertFalse($review->likedByUsers()->whereKey($user->id)->exists());
        $this->assertDatabaseMissing('review_likes', $like);

        // 8
        $review->likedByUsers()->syncWithoutDetaching($user->id);
        $this->assertDatabaseHas('review_likes', $like);

        // 9
        $user->delete();

        // 10
        $this->assertFalse($review->likedByUsers()->whereKey($user->id)->exists());
        $this->assertDatabaseMissing('review_likes', $like);
    }
}
