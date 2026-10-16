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

class FavoriteTest extends TestCase
{
    use RefreshDatabase;

    /** ゲストが書籍一覧のお気に入りリンクを押下するとログイン画面にリダイレクトする */
    public function testGuestCannotViewFavorites(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. ゲストとして書籍一覧画面にアクセス
        // 3. お気に入り一覧ボタンのリンク先にアクセス
        // 4. テスト: ログイン画面にリダイレクトする

        // 1
        $this->createTestDataFromSeeder();
        $favoritesUrl = route('favorites.index');

        // 2
        $this->get('/')
            ->assertOk()
            ->assertViewIs('books.index')
            ->assertSee('href="' . $favoritesUrl . '"', false);

        // 3,4
        $this->get($favoritesUrl)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** 認証ユーザーはページが正常に表示される */
    public function testFavoritesView(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. 認証ユーザーとして書籍一覧画面にアクセス
        // 3. お気に入り一覧ボタンのリンク先にアクセス
        // 4. テスト: お気に入り一覧画面に遷移する

        // 1
        $this->createTestDataFromSeeder();
        $user = User::firstOrFail();
        $favoritesUrl = route('favorites.index');

        // 2
        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertViewIs('books.index')
            ->assertSee('href="' . $favoritesUrl . '"', false);

        // 3,4
        $this->get($favoritesUrl)
            ->assertOk()
            ->assertViewIs('favorites.index')
            ->assertSeeText('お気に入り一覧');
    }

    /** 
     * お気に入り一覧画面でお気に入り登録した書籍一覧がページネーションされ表示されている
     */
    public function testFavoritesPagination(): void
    {
        // 1. 11件をお気に入り登録したユーザーを作成する
        // 2. 1で作成したユーザーが未登録の書籍を作成する
        // 3. 1で作成したユーザーとして認証し、お気に入り一覧画面アクセス
        // 4. テスト: 1ページ目10件・2ページ目1件で、ページネーションされて表示されているか
        // 5. テスト: お気に入り登録していない書籍が表示されていないか

        // 1
        $this->createTestDataFromSeeder();
        $favoriteBooks = Book::all();
        $user = User::factory()->create();
        $user->favoriteBooks()->attach($favoriteBooks->modelKeys());

        // 2
        $otherBook = User::firstOrFail()->registeredBooks()->create([
            'title' => 'お気に入り未登録の書籍',
            'author' => '対象外の著者',
            'isbn' => '9780000000000',
            'published_date' => '2026-01-01',
        ]);

        // 3
        $this->actingAs($user);
        $firstPage = $this->get(route('favorites.index'))
            ->assertOk()->assertViewIs('favorites.index');

        // 4
        $firstBooks = $firstPage->viewData('books');
        $this->assertCount(10, $firstBooks);
        $this->assertSame(1, $firstBooks->currentPage());
        $this->assertSame(11, $firstBooks->total());
        $this->assertSame(10, $firstBooks->perPage());

        $secondUrl = $firstBooks->url(2);
        $firstPage->assertSee('href="' . $secondUrl . '"', false);
        $secondPage = $this->get($secondUrl)
            ->assertOk()->assertViewIs('favorites.index');
        $secondBooks = $secondPage->viewData('books');
        $this->assertCount(1, $secondBooks);
        $this->assertSame(2, $secondBooks->currentPage());

        $shownIds = array_merge($firstBooks->getCollection()->modelKeys(), $secondBooks->getCollection()->modelKeys());
        $this->assertCount(11, array_unique($shownIds));
        $this->assertEqualsCanonicalizing($favoriteBooks->modelKeys(), $shownIds);

        foreach ($firstBooks as $book) {
            $firstPage->assertSeeText($book->title);
            $secondPage->assertDontSee('href="' . route('books.show', $book) . '"', false);
        }
        foreach ($secondBooks as $book) {
            $secondPage->assertSeeText($book->title);
            $firstPage->assertDontSee('href="' . route('books.show', $book) . '"', false);
        }

        // 5
        foreach ([$firstPage, $secondPage] as $page) {
            $page->assertDontSeeText($otherBook->title)
                ->assertDontSee('href="' . route('books.show', $otherBook) . '"', false);
        }
    }

    /** 書籍タイトルを押すと対象書籍の詳細画面に遷移する */
    public function testBookShowLink(): void
    {
        // 1. 1件以上お気に入り登録したユーザーを作成する
        // 2. 1で作成したユーザーとして認証し、お気に入り一覧画面アクセス
        // 3. テスト: 書籍タイトルに書籍詳細画面へのリンクが存在するか
        // 4. テスト: リンク先を辿ると対象の書籍の詳細画面に遷移するか

        // 1
        $this->createTestDataFromSeeder();
        $user = User::firstOrFail();
        $book = $user->favoriteBooks()->firstOrFail();
        $showUrl = route('books.show', $book);

        // 2
        $response = $this->actingAs($user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertViewIs('favorites.index');

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//h3/a[@href="' . $showUrl . '"]');
        $this->assertCount(1, $links);
        $this->assertSame($book->title, $xpath->evaluate('normalize-space(.)', $links->item(0)));

        // 4
        $this->get($links->item(0)->getAttribute('href'))
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertViewHas('book', fn(Book $shownBook) => $shownBook->is($book))
            ->assertSeeText($book->title);
    }

    /**
     * seederからテストデータ生成
     * 
     * @return void
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
