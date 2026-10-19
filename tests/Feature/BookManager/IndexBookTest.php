<?php

namespace Tests\Feature\BookManager;

use App\Models\User;
use App\Models\Book;
use Database\Seeders\UserSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\ReviewSeeder;
use Database\Seeders\FavoriteSeeder;
use Database\Seeders\ReviewLikeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexBookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ゲスト、認証ユーザー共に'/','/books'にアクセスし、書籍一覧画面が表示されることを確認する
     */
    public function testBookIndex(): void
    {
        // 1.ゲストが '/'にアクセスする
        // 2.テスト: 意図した画面('books.index')が表示される
        // 3.ゲストが '/books'にアクセスする
        // 4.テスト: '/'にリダイレクトされ、遷移先で'books.index'が表示される
        // 5.認証ユーザーが '/'にアクセスする
        // 6.テスト: 意図した画面('books.index')が表示される
        // 7.認証ユーザーが '/books'にアクセスする
        // 8.テスト: '/'にリダイレクトされ、遷移先で'books.index'が表示される

        $this->createTestDataFromSeeder();

        // 1,2
        $this->get('/')
            ->assertOk()
            ->assertViewIs('books.index');

        // 3,4
        $this->get('/books')->assertRedirect('/');
        $this->followingRedirects()->get('/books')
            ->assertOk()
            ->assertViewIs('books.index');

        // 5,6
        $this->actingAs(User::firstOrFail());
        $this->get('/')
            ->assertOk()
            ->assertViewIs('books.index');

        // 7,8
        $this->get('/books')->assertRedirect('/');
        $this->followingRedirects()->get('/books')
            ->assertOk()
            ->assertViewIs('books.index');
    }

    /**
     * 書籍一覧が１０件でページネーションされている
     */
    public function testBookIndexPagination(): void
    {
        // 1. '/'にアクセスする
        // 2. テスト: 1ページ目に書籍が10件表示されている
        // 3. 2ページ目のリンクにアクセス
        // 4. テスト: 2ページ目に書籍が１件表示されている

        $this->createTestDataFromSeeder();

        // 1
        $firstPage = $this->get('/')
            ->assertOk()
            ->assertViewIs('books.index');
        $firstPageBooks = $firstPage->viewData('books');

        // 2
        $this->assertSame(1, $firstPageBooks->currentPage());
        $this->assertCount(10, $firstPageBooks);
        $this->assertSame(11, $firstPageBooks->total());
        foreach ($firstPageBooks as $book) {
            $firstPage->assertSeeText($book->title);
        }

        // 3
        $secondPageUrl = $firstPageBooks->url(2);
        $firstPage->assertSee('href="' . $secondPageUrl . '"', false);
        $secondPage = $this->get($secondPageUrl)
            ->assertOk()
            ->assertViewIs('books.index');

        // 4
        $secondPageBooks = $secondPage->viewData('books');
        $this->assertCount(1, $secondPageBooks);
        $this->assertSame(2, $secondPageBooks->currentPage());
        $secondPage->assertSeeText($secondPageBooks->first()->title);
        $firstPage->assertDontSeeText($secondPageBooks->first()->title);
        foreach ($firstPageBooks as $book) {
            $secondPage->assertDontSeeText($book->title);
        }
    }



    /**
     * ゲスト、認証ユーザー共に書籍タイトルから詳細画面に遷移できる
     */
    public function testBookShowLink(): void
    {
        // 1.ゲストが '/'にアクセスする
        // 2.書籍詳細画面へのリンクがあることを確認し、リンク先にアクセス
        // 3.テスト: 意図した画面('books.show')が表示される
        // 4.認証ユーザーが '/'にアクセスする
        // 5.書籍詳細画面へのリンクがあることを確認し、リンク先にアクセス
        // 6.テスト: 意図した画面('books.show')が表示される

        $this->createTestDataFromSeeder();
        $book = Book::firstOrFail();
        $showUrl = route('books.show', $book);

        // 1,2
        $this->get('/')
            ->assertOk()
            ->assertSee('href="' . $showUrl . '"', false);

        // 3
        $this->get($showUrl)
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertViewHas('book', fn (Book $shownBook) => $shownBook->is($book));

        // 4,5
        $this->actingAs(User::firstOrFail());
        $this->get('/')
            ->assertOk()
            ->assertSee('href="' . $showUrl . '"', false);

        // 6
        $this->get($showUrl)
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertViewHas('book', fn (Book $shownBook) => $shownBook->is($book));
    }

    /**
     * ゲストは書籍登録にアクセスしたらログイン画面に遷移される
     */
    public function testGuestCannotCreateBook(): void
    {
        // 1.ゲストが '/'にアクセスする
        // 2.書籍登録リンクが存在することを確認し、リンク先にアクセス
        // 3.テスト: ログイン画面にリダイレクト

        $createUrl = route('books.create');

        // 1,2
        $this->get('/')
            ->assertOk()
            ->assertSee('href="' . $createUrl . '"', false);

        // 3
        $this->get($createUrl)->assertRedirect(route('login'));
    }

    /**
     * 認証ユーザーは書籍登録画面に遷移する
     */
    public function testAuthUserCanViewCreate(): void
    {
        // 1.認証ユーザーが '/'にアクセスする
        // 2.書籍登録リンクが存在することを確認し、リンク先にアクセス
        // 3.テスト: 書籍登録画面に遷移する

        $user = User::factory()->create();
        $this->actingAs($user);
        $createUrl = route('books.create');

        // 1,2
        $this->get('/')
            ->assertOk()
            ->assertSee('href="' . $createUrl . '"', false);

        // 3
        $this->get($createUrl)
            ->assertOk()
            ->assertViewIs('books.create');
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
