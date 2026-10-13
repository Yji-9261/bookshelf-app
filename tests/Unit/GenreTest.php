<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Genre;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * booksのリレーションが適切か
     */
    public function test_books(): void
    {
        /**
         * 1. $user = User::create([...])で作成
         * 2. $book = $user->registeredBooks()->create([...])で書籍作成
         * 3. $genre = Genre::create(['name' => 'テスト'])でジャンル作成
         * 4. $genre->books()->syncWithoutDetaching($book->id)でリレーション構築
         * 5. テスト:$bookと$genreが紐つけられているか
         *    $genre->books()->whereKey($book->id)->exists()がtrue
         *    book_genreテーブルに'book_id' => $book->id, 'genre_id' => $genre->idの行が存在する
         * 6. $genre->books()->detach($book->id)を使用してリレーション解除
         * 7. テスト:$bookと$genreのリレーションが解除されているか
         *    $genre->books()->whereKey($book->id)->exists()がfalse
         *    book_genreテーブルに'book_id' => $book->id, 'genre_id' => $genre->idの行が存在しない
         * 8. $genre->books()->syncWithoutDetaching($book->id)でリレーション構築
         * 9. $book->delete()で書籍削除
         * 10. テスト:$bookと$genreのリレーションが解除されているか
         *    $genre->books()->whereKey($book->id)->exists()がfalse
         *    book_genreテーブルに'book_id' => $book->id, 'genre_id' => $genre->idの行が存在しない
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

        $genre = Genre::create(['name' => 'テスト']);
        $bookGenre = [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ];

        $genre->books()->syncWithoutDetaching($book->id);

        $this->assertTrue($genre->books()->whereKey($book->id)->exists());
        $this->assertDatabaseHas('book_genre', $bookGenre);

        $genre->books()->detach($book->id);

        $this->assertFalse($genre->books()->whereKey($book->id)->exists());
        $this->assertDatabaseMissing('book_genre', $bookGenre);

        $genre->books()->syncWithoutDetaching($book->id);
        $this->assertDatabaseHas('book_genre', $bookGenre);

        $book->delete();

        $this->assertFalse($genre->books()->whereKey($book->id)->exists());
        $this->assertDatabaseMissing('book_genre', $bookGenre);
    }
}
