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

class IndexBookTest extends TestCase
{
    use RefreshDatabase;

    /** API書籍一覧を取得し期待通りのレスポンスが返ってくる */
    public function testBooksIndex(): void
    {
        // 1. 21件の書籍データを作成する
        // 2. GET /api/v1/booksにアクセス
        // 3. テスト: json出力構造が期待通りか
        // 4. デフォルトのページネーションは20件となっているか

        // 1
        $books = Book::factory()->count(21)->create();
        $genre = Genre::create(['name' => '小説']);
        $books->each(function ($book) use ($genre) {
            $book->genres()->sync($genre->id);
        });

        // 2~4
        $this->getBooks()
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'author',
                        'isbn',
                        'published_date',
                        'image_url',
                        'description',
                        'genres' => ['*' => ['id', 'name']],
                        'reviews_avg_rating',
                        'reviews_count',
                    ]
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ])
            // 1ページ目は21件中20件の表示となっている
            ->assertJsonCount(20, 'data');
    }

    /** 書籍一覧のページネーションとページ指定は想定通りか */
    public function testBooksPagination(): void
    {
        // 1. Seederからテストデータ生成
        // 2. GET /api/v1/books?per_page=1&page=1でアクセス
        // 3. テスト: 1冊の書籍情報と、1件/ページのページ情報が返る
        // 4. GET /api/v1/books?per_page=1&page=2でアクセス
        // 5. テスト: 2冊目の書籍情報と、1件/ページのページ情報が返る

        // 1
        $this->createTestDataFromSeeder();
        $books = Book::with('genres')->withCount('reviews')->orderBy('id')->get();

        // 2〜5: pageを明示して1ページ目と2ページ目を取得・検証
        foreach ([1, 2] as $page) {
            $book = $books[$page - 1];
            $response = $this->getBooks(['per_page' => 1, 'page' => $page])
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('meta.current_page', $page)
                ->assertJsonPath('meta.per_page', 1)
                ->assertJsonPath('meta.from', $page)
                ->assertJsonPath('meta.to', $page)
                ->assertJsonPath('meta.total', $books->count())
                ->assertJsonPath('meta.last_page', $books->count());

            $this->assertBookData($response, $book);
        }
    }

    /** 書籍一覧のkeyword検索は想定通りか */
    public function testBooksKeywordSearch(): void
    {
        // 1. 1冊目=title: テスト, author: author1
        //    2冊目=title: 検証, author: author2
        //    の 2書籍を作成する
        // 2. GET /api/v1/books?keyword='テスト'でアクセス
        // 3. テスト: 1冊目のレスポンスのみ返る
        // 4. GET /api/v1/books?keyword='2'でアクセス
        // 5. テスト: 2冊目のレスポンスのみ返る
        // 6. GET /api/v1/books?keyword='author'でアクセス
        // 7. テスト: 1と2の書籍レスポンスが返る

        $firstBook = Book::factory()->create(['title' => 'テスト', 'author' => 'author1']);
        $secondBook = Book::factory()->create(['title' => '検証', 'author' => 'author2']);

        $this->assertBookIds($this->getBooks(['keyword' => 'テスト']), [$firstBook->id]);
        $this->assertBookIds($this->getBooks(['keyword' => '2']), [$secondBook->id]);
        $this->assertBookIds($this->getBooks(['keyword' => 'author']), [$firstBook->id, $secondBook->id]);
    }

    /** 書籍一覧のgenre検索は想定通りか */
    public function testBooksGenreSearch(): void
    {
        // 1. seederからテストデータ作成
        // 2. GET /api/v1/books?genre='小説'でアクセス
        // 3. テスト: ジャンル小説のものだけ取得できているか
        // 4. GET /api/v1/books?genre='旅行'でアクセス
        // 5. テスト: 1件も取得されない

        // 1
        $this->createTestDataFromSeeder();
        $novel = Genre::where('name', '小説')->firstOrFail();
        $novelBookIds = $novel->books()->pluck('books.id')->all();
        $this->assertNotEmpty($novelBookIds);

        // 2
        $response = $this->getBooks(['genre' => '小説']);

        // 3
        $this->assertBookIds($response, $novelBookIds);
        foreach ($response->json('data') as $book) {
            $this->assertContains('小説', array_column($book['genres'], 'name'));
        }

        // 4,5
        $travel = Genre::where('name', '旅行')->firstOrFail();
        $this->assertFalse($travel->books()->exists());
        $this->assertBookIds($this->getBooks(['genre' => '旅行']), []);
    }

    /** 書籍一覧のページネーション異常値入力時のテスト */
    public function testPerPageValidation(): void
    {
        // 1. GET /api/v1/books?per_page=abcでアクセス
        // 2. テスト: 「数値を入力してください」がエラーレスポンスとして返る
        // 3. GET /api/v1/books?per_page=0でアクセス
        // 4. テスト: 「1以上の数値を入力してください」がエラーレスポンスとして返る
        // 5. GET /api/v1/books?per_page=101でアクセス
        // 6. テスト: 「100以下の数値を入力してください」がエラーレスポンスとして返る

        $cases = [
            ['abc', '数値を入力してください'],
            [0, '1以上の数値を入力してください'],
            [101, '100以下の数値を入力してください'],
        ];

        foreach ($cases as [$perPage, $message]) {
            $this->requestBooks(['per_page' => $perPage])
                ->assertStatus(422)
                ->assertJson([
                    'errors' => [
                        'per_page' => [$message],
                    ]
                ]);
        }
    }
    /** 書籍一覧のページ異常値入力時のテスト */
    public function testPageValidation(): void
    {
        // 1. GET /api/v1/books?page=abcでアクセス
        // 2. テスト: 「数値を入力してください」がエラーレスポンスとして返る
        // 3. GET /api/v1/books?page=0でアクセス
        // 4. テスト: 「1以上の数値を入力してください」がエラーレスポンスとして返る

        $cases = [
            ['abc', '数値を入力してください'],
            [0, '1以上の数値を入力してください'],
        ];

        foreach ($cases as [$page, $message]) {
            $this->requestBooks(['page' => $page])
                ->assertStatus(422)
                ->assertJson([
                    'errors' => [
                        'page' => [$message],
                    ]
                ]);
        }
    }

    /** 書籍一覧のkeyword異常値入力時のテスト */
    public function testKeywordValidation(): void
    {
        // 1. GET /api/v1/books?keyword[]=testでアクセス
        // 2. テスト: 「キーワードは文字列で入力して下さい」がエラーレスポンスとして返る
        // 3. GET /api/v1/books?keywordに256文字でアクセス
        // 4. テスト: 「キーワードを255文字以内で入力してください」がエラーレスポンスとして返る

        $cases = [
            [['test'], 'キーワードは文字列で入力して下さい'],
            [str_repeat('あ', 256), 'キーワードを255文字以内で入力してください'],
        ];

        foreach ($cases as [$keyword, $message]) {
            $this->requestBooks(['keyword' => $keyword])
                ->assertStatus(422)
                ->assertJson([
                    'errors' => [
                        'keyword' => [$message],
                    ]
                ]);
        }
    }

    /** 書籍一覧のgenre異常値入力時のテスト */
    public function testGenreValidation(): void
    {
        // 1. GET /api/v1/books?genre=testでアクセス
        // 2. テスト: 「ジャンルが存在しません」がエラーレスポンスとして返る

        $this->requestBooks(['genre' => 'test'])
            ->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'genre' => ['ジャンルが存在しません'],
                ]
            ]);
    }

    /** クエリパラメータを付けて書籍一覧APIにアクセスする */
    private function getBooks(array $query = []): TestResponse
    {
        return $this->requestBooks($query)->assertOk();
    }

    /** 正常・異常の両ケースで使う書籍一覧APIへのリクエスト */
    private function requestBooks(array $query = []): TestResponse
    {
        $url = '/api/v1/books';
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $this->getJson($url);
    }

    /** 1件の書籍情報をDBの値と照合する */
    private function assertBookData(TestResponse $response, Book $book, int $index = 0): void
    {
        foreach (['id', 'title', 'author', 'isbn', 'published_date', 'image_url', 'description'] as $field) {
            $response->assertJsonPath("data.{$index}.{$field}", $book->$field);
        }
        $this->assertEqualsCanonicalizing(
            $book->genres->map(fn($genre) => ['id' => $genre->id, 'name' => $genre->name])->all(),
            $response->json("data.{$index}.genres")
        );
        $this->assertEqualsWithDelta($book->reviews_avg_rating, $response->json("data.{$index}.reviews_avg_rating"), 0.00001);
        $response->assertJsonPath("data.{$index}.reviews_count", $book->reviews_count);
    }

    /** レスポンスに期待する書籍だけが含まれていることを確認する */
    private function assertBookIds(TestResponse $response, array $expectedIds): void
    {
        $response->assertJsonCount(count($expectedIds), 'data');
        $this->assertEqualsCanonicalizing($expectedIds, array_column($response->json('data'), 'id'));
        $response->assertJsonPath('meta.total', count($expectedIds));
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
