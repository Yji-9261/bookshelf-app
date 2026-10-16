<?php

namespace Tests\Feature;

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
use Laravel\Sail\Console\PublishCommand;
use Tests\TestCase;

class ShowGenreTest extends TestCase
{
    use RefreshDatabase;

    /** ゲストがジャンル詳細リンクを押下するとログイン画面にリダイレクトする */
    public function testGuestCannotViewGenre(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. ゲストとしてジャンル詳細画面にアクセス
        // 3. テスト: ログイン画面にリダイレクトする

        // 1
        $this->createTestDataFromSeeder();
        $genre = Genre::firstOrFail();

        // 2
        $this->get(route('genres.show', $genre))
            ->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** 認証ユーザーはページが正常に表示される */
    public function testGenreView(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. 全ての書籍に「小説」ジャンルを紐つける
        // 3. 認証ユーザーとして「小説」ジャンル詳細画面にアクセス
        // 4. テスト: ジャンル名と紐づく書籍がページネーション（1ページ目10件・2ページ目1件）で表示される

        // 1
        $this->createTestDataFromSeeder();
        // 2
        $genre = Genre::where('name', '小説')->firstOrFail();

        // 書籍は11冊存在する
        $books = Book::all();
        $genre->books()->syncWithoutDetaching($books->modelKeys());

        // 3,4

        // 1ページ目には10冊の書籍が存在するか確認
        $firstPage = $this->actingAs(User::firstOrFail())
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertViewIs('genres.show')
            ->assertViewHas('genre', fn(Genre $shownGenre) => $shownGenre->is($genre))
            ->assertSeeText('ジャンル: ' . $genre->name);
        $firstBooks = $firstPage->viewData('books');
        $this->assertCount(10, $firstBooks);
        $this->assertSame(1, $firstBooks->currentPage());
        $this->assertSame(10, $firstBooks->perPage());
        $this->assertSame(11, $firstBooks->total());

        // 2ページ目には1冊の書籍が存在するか確認
        $secondUrl = $firstBooks->url(2);
        $firstPage->assertSee('href="' . $secondUrl . '"', false);
        $secondPage = $this->get($secondUrl)
            ->assertOk()
            ->assertViewIs('genres.show')
            ->assertViewHas('genre', fn(Genre $shownGenre) => $shownGenre->is($genre))
            ->assertSeeText('ジャンル: ' . $genre->name);
        $secondBooks = $secondPage->viewData('books');
        $this->assertCount(1, $secondBooks);
        $this->assertSame(2, $secondBooks->currentPage());

        // 全ページに全ての書籍が存在しているか確認
        $shownIds = array_merge($firstBooks->getCollection()->modelKeys(), $secondBooks->getCollection()->modelKeys());
        $this->assertCount(11, array_unique($shownIds));
        $this->assertEqualsCanonicalizing($books->modelKeys(), $shownIds);

        // 1ページ目に想定通りの書籍の一覧となっているか
        foreach ($firstBooks as $book) {
            $firstPage->assertSeeText($book->title);
            $secondPage->assertDontSee('href="' . route('books.show', $book) . '"', false);
        }
        // 2ページ目に想定通りの書籍の一覧となっているか
        foreach ($secondBooks as $book) {
            $secondPage->assertSeeText($book->title);
            $firstPage->assertDontSee('href="' . route('books.show', $book) . '"', false);
        }
    }

    /** 
     * 書籍タイトルを押すと書籍詳細画面に遷移する
     */
    public function testBookShowLink(): void
    {
        // 1. seederからテストデータ作成
        // 2. 認証ユーザーとして書籍に紐ついているジャンルの詳細画面にアクセス
        // 3. テスト: 書籍タイトルに書籍詳細画面へのリンクが存在するか
        // 4. テスト: リンク先を辿ると対象の書籍の詳細画面に遷移するか

        // 1
        $this->createTestDataFromSeeder();
        $genre = Genre::whereHas('books')->firstOrFail();

        // 2
        $response = $this->actingAs(User::firstOrFail())
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertViewIs('genres.show');
        $book = $response->viewData('books')->first();
        $this->assertNotNull($book);
        $showUrl = route('books.show', $book);

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//a[@href="' . $showUrl . '"]');
        $this->assertCount(1, $links);
        $titles = $xpath->query('.//h3', $links->item(0));
        $this->assertCount(1, $titles);
        $this->assertSame($book->title, $xpath->evaluate('normalize-space(.)', $titles->item(0)));

        // 4
        $this->get($links->item(0)->getAttribute('href'))
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertViewHas('book', fn(Book $shownBook) => $shownBook->is($book))
            ->assertSeeText($book->title);
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
