<?php

namespace Tests\Feature\BookManager;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\GenreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AdvancedBookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * キーワード検索を実行した時に、期待通りの結果を返す
     */
    public function testKeywordSearch(): void {
        // 1. 1冊目=title: テスト, author: author1
        //    2冊目=title: 検証, author: author2
        //    の 2書籍を作成する
        // 2. 「テスト」でタイトル全文一致検索
        // 3. テスト: 1冊目の書籍が表示される
        // 4. 「スト」で部分一致検索
        // 5. テスト: 1冊目の書籍が表示される
        // 6. 「2」で著者部分一致検索
        // 7. テスト: 2冊目の書籍が表示される
        // 8. 「author」で著者部分一致検索
        // 9. テスト: 1と2の書籍が表示される
        // 10. 「authors」で検索
        // 11. テスト: 一冊も表示されない

        // 1
        $firstBook = Book::factory()->create(['title' => 'テスト', 'author' => 'author1']);
        $secondBook = Book::factory()->create(['title' => '検証', 'author' => 'author2']);

        // 2,3
        $this->assertSeeBookTitleAuthor($this->getBooks(['keyword' => 'テスト']), [$firstBook], [$secondBook]);

        // 4,5
        $this->assertSeeBookTitleAuthor($this->getBooks(['keyword' => 'スト']), [$firstBook], [$secondBook]);

        // 6,7
        $this->assertSeeBookTitleAuthor($this->getBooks(['keyword' => '2']), [$secondBook], [$firstBook]);

        // 8,9
        $this->assertSeeBookTitleAuthor($this->getBooks(['keyword' => 'author']), [$firstBook, $secondBook], []);

        // 10,11
        $this->assertSeeBookTitleAuthor($this->getBooks(['keyword' => 'authors']), [], [$firstBook, $secondBook]);
    }

    /**
     * ジャンルフィルタを実行した時に、期待通りの結果を返す
     */
    public function testGenreFilter(): void {
        // 1. 1冊目=title: テスト, author: author1
        //    2冊目=title: 検証, author: author2
        //    の 2書籍を作成する
        // 2. ジャンルをseederから作成し、1冊目にジャンルID1,2を、2冊目にジャンルID1,3を割り当てる
        // 3. 「ジャンルID1」でフィルタリング
        // 4. テスト: 1,2冊の書籍が表示される
        // 5. 「ジャンルID2」でフィルタリング
        // 6. テスト: 1冊目の書籍が表示される
        // 7. 「ジャンルID3」でフィルタリング
        // 8. テスト: 2冊目の書籍が表示される
        // 9. 「ジャンルID4」でフィルタリング
        // 10. テスト: 1冊も表示されない

        // 1
        $firstBook = Book::factory()->create(['title' => 'テスト', 'author' => 'author1']);
        $secondBook = Book::factory()->create(['title' => '検証', 'author' => 'author2']);

        // 2
        $this->createTestGenresFromSeeder();
        $firstBook->genres()->sync([1, 2]);
        $secondBook->genres()->sync([1, 3]);

        // 3,4
        $this->assertSeeBookTitleAuthor($this->getBooks(['genre' => 1]), [$firstBook, $secondBook], []);

        // 5,6
        $this->assertSeeBookTitleAuthor($this->getBooks(['genre' => 2]), [$firstBook], [$secondBook]);

        // 7,8
        $this->assertSeeBookTitleAuthor($this->getBooks(['genre' => 3]), [$secondBook], [$firstBook]);

        // 9,10
        $this->assertSeeBookTitleAuthor($this->getBooks(['genre' => 4]), [], [$firstBook, $secondBook]);
    }

    /**
     * ソートを実行した時に、期待通りの結果を返す
     */
    public function testSort(): void {
        // 1. 1冊目=title: 1_title, author: author1, 'created_at': '2000-01-01'
        //    2冊目=title: 3_title, author: author2, 'created_at': '2026-01-01'
        //    3冊目=title: 2_title, author: author2, 'created_at': '1970-01-01'
        //    の 3書籍を作成する
        // 2. 1冊目にレビュー1,1冊目にレビュー5,1冊目にレビュー3を設定する
        // 3. 「oldest」でソート
        // 4. テスト: 3,1,2の順で書籍が表示されている
        // 5. 「newest」でソート
        // 6. テスト: 2,1,3の順で書籍が表示されている
        // 7. 「rating」でソート
        // 8. テスト: 2,3,1の順で書籍が表示されている
        // 9. 「title」でソート
        // 10. テスト: 1,3,2の順で書籍が表示されている

        // 1
        // ソートの新しい(古い)順は登録日
        $books = Book::factory()->createMany([
            ['title' => '1_title', 'author' => 'author1', 'created_at' => '2000-01-01'],
            ['title' => '3_title', 'author' => 'author2', 'created_at' => '2026-01-01'],
            ['title' => '2_title', 'author' => 'author3', 'created_at' => '1970-01-01'],
        ]);

        // 2
        $this->createReview($books[0], 1);
        $this->createReview($books[1], 5);
        $this->createReview($books[2], 3);

        // 3,4
        $this->getBooks(['sort' => 'oldest'])->assertSeeInOrder([
            $books[2]->title,
            $books[0]->title,
            $books[1]->title,
        ]);

        // 5,6
        $this->getBooks(['sort' => 'newest'])->assertSeeInOrder([
            $books[1]->title,
            $books[0]->title,
            $books[2]->title,
        ]);

        // 7,8
        $this->getBooks(['sort' => 'rating'])->assertSeeInOrder([
            $books[1]->title,
            $books[2]->title,
            $books[0]->title,
        ]);

        // 9,10
        $this->getBooks(['sort' => 'title'])->assertSeeInOrder([
            $books[0]->title,
            $books[2]->title,
            $books[1]->title,
        ]);
    }

    /**
     * 応用機能検索・フィルタ・ソートを複合して実行した時、期待通りの結果を返す
     */
    public function testKeywordFilterSort(): void {
        // 1. 書籍を作成する
        //    1冊目=title: title3, author: author3, Genre:1,2
        //    2冊目=title: title2, author: author2, Genre:1,3
        //    3冊目=title: title1, author: author1, Genre:3,4
        // 2. キーワードに「title」,ジャンルに「1」,ソートに「title」で検索実行
        // 3. テスト: 2,1の順に書籍が表示される
        // 4. キーワードに「3」,ジャンルに「3」,ソートに「title」で検索実行
        // 5. テスト: 書籍が1冊も表示されない
        // 6. キーワードに「author」,ジャンルに「3」,ソートに「title」で検索実行
        // 7. テスト: 3,2の順に書籍が表示される

        // 1
        // 要確認: ソートの新しい(古い)順は登録日か出版日か?
        $books = Book::factory()->createMany([
            ['title' => 'title3', 'author' => 'author3'],
            ['title' => 'title2', 'author' => 'author2'],
            ['title' => 'title1', 'author' => 'author1'],
        ]);
        $this->createTestGenresFromSeeder();
        $books[0]->genres()->sync([1, 2]);
        $books[1]->genres()->sync([1, 3]);
        $books[2]->genres()->sync([3, 4]);

        // 2,3
        $this->getBooks([
            'keyword' => 'title',
            'genre' => 1,
            'sort' => 'title',
        ])
            ->assertSeeInOrder([
                $books[1]->title,
                $books[0]->title,
            ])
            ->assertDontSeeText($books[2]->title);

        // 4,5
        $this->assertSeeBookTitleAuthor(
            $this->getBooks([
                'keyword' => '3',
                'genre' => 3,
                'sort' => 'title',
            ]),
            [],
            $books
        );

        // 6,7
        $this->getBooks([
            'keyword' => 'author',
            'genre' => 3,
            'sort' => 'title',
        ])
            ->assertSeeInOrder([
                $books[2]->title,
                $books[1]->title,
            ])
            ->assertDontSeeText($books[0]->title);
    }

    /**
     * GoogleBooksApiによるisbn検索を実行した時、期待通りの結果を返す
     */
    public function testSearchIsbn(): void {
        // 1. 認証ユーザーとして/books/createにアクセスする
        // 2. Google Books APIの応答をLaravel標準のHttp::fake()で固定する
        // 3. ISBN「9784798147390」で検索用エンドポイントにアクセスする
        // 4. テスト: タイトル・共著者・説明・画像URL・出版日が期待どおり返る
        // 5. テスト: Google Books APIへのリクエストが1回送られる

        // 1,2
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get(route('books.create'))
            ->assertOk();

        // 3
        $isbn = '9784798147390';
        $imageUrl = 'http://books.google.com/books/content?id=JUWGEAAAQBAJ&printsec=frontcover&img=1&zoom=5&edge=curl&source=gbs_api';
        $description = 'APIの設計パターンを解説する書籍です。';

        // 意図しない通信パターンによる外部アクセスを制限する
        Http::preventStrayRequests();
        Http::fake([
            'https://www.googleapis.com/books/v1/volumes*' => Http::response([
                'items' => [
                    [
                        'volumeInfo' => [
                            'title' => 'APIデザイン・パターン',
                            'authors' => ['JJGeewax', '松田晃一'],
                            'description' => $description,
                            'imageLinks' => ['thumbnail' => $imageUrl],
                            'publishedDate' => '2022-08-26',
                        ],
                    ],
                ],
            ]),
        ]);

        // 4,5
        $this->getJson('/books/isbn/' . $isbn)
            ->assertOk()
            ->assertExactJson([
                'title' => 'APIデザイン・パターン',
                'author' => 'JJGeewax,松田晃一',
                'description' => $description,
                'image_url' => $imageUrl,
                'published_date' => '2022-08-26',
            ]);

        // 6
        Http::assertSentCount(1);
    }

    /**
     * seederからテストジャンル生成
     */
    private function createTestGenresFromSeeder(): void {
        $this->seed([
            GenreSeeder::class,
        ]);
    }

    /**
     * クエリパラメータを付けて書籍一覧APIにアクセスする
     *
     * @param  array  $query  クエリパラメータ (Key..フィールド Value..入力内容)
     */
    private function getBooks(array $query = []): TestResponse {
        return $this->requestBooks($query)->assertOk();
    }

    /**
     * 正常・異常の両ケースで使う書籍一覧APIへのリクエスト
     *
     * @param  array  $query  クエリパラメータ (Key..フィールド Value..入力内容)
     */
    private function requestBooks(array $query = []): TestResponse {
        $url = '/books';
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $this->get($url);
    }

    /**
     * 期待される表示・非表示書籍を検証
     *
     * @param  array|Collection  $visibleBooks  表示書籍models
     * @param  array|Collection  $invisibleBooks  非表示書籍models
     */
    private function assertSeeBookTitleAuthor(
        TestResponse $response,
        array|Collection $visibleBooks,
        array|Collection $invisibleBooks): TestResponse {
        foreach ($visibleBooks as $book) {
            $response->assertSeeText($book->title);
            $response->assertSeeText($book->author);
        }

        foreach ($invisibleBooks as $book) {
            $response->assertDontSeeText($book->title);
            $response->assertDontSeeText($book->author);
        }

        return $response;
    }

    /**
     * 対象書籍にレビュー作成
     */
    private function createReview(Book $book, float $rating): void {
        Review::factory()->create([
            'book_id' => $book->id,
            'rating' => $rating,
        ]);
    }
}
