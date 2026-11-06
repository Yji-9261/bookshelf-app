<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateBookTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * ゲストは書籍を登録できない
     */
    public function testGuestCannotCreateBook(): void {
        // sanctum認証を行わない場合、認証エラーを確認
        $bookRecord = $this->validBookData();
        $this->postJson('/api/v1/books', $bookRecord)
            ->assertUnauthorized();

        // DB 登録されていないことを確認する
        $this->assertDatabaseEmpty('books');
    }

    /**
     * 認証ユーザーはAPI書籍登録を正常なリクエストで実行したとき、正常に終了する
     */
    public function testBooksCreate(): void {
        // 1. ユーザーを作成し、validBookData()で小説ジャンル・有効な登録データを作成する
        // 2. 「小説」「エッセイ」の2種類のジャンルを用意する
        // 3. 有効な書籍情報とジャンル名の配列をPOST /api/v1/booksへ送信する
        // 4. テスト: HTTP 201と、書籍情報・2ジャンル・平均評価0・レビュー数0が返る
        // 5. テスト: 認証ユーザーを登録者として書籍が1件保存され、指定した2ジャンルが紐付く

        // 1
        $bookRecord = $this->validBookData();
        unset($bookRecord['user_id']);

        // 2
        $novel = Genre::where('name', $bookRecord['genres'][0])->firstOrFail();
        $essay = Genre::create(['name' => 'エッセイ']);
        $bookRecord['genres'][] = $essay->name;

        // 3
        // あらかじめ書籍テーブルが空であることを検証
        $this->assertDatabaseEmpty('books');

        // 認証しpostアクセス
        Sanctum::actingAs($this->user, ['*']);
        $response = $this->postJson('/api/v1/books', $bookRecord)->assertCreated();
        $book = Book::where('isbn', $bookRecord['isbn'])->firstOrFail();

        // latest()はID取得時の順序で、load('genres')の返却順は保証しないため順不同で照合
        $this->assertEqualsCanonicalizing([
            ['id' => $novel->id, 'name' => $novel->name],
            ['id' => $essay->id, 'name' => $essay->name],
        ], $response->json('data.genres'));

        // 4
        $response->assertExactJson([
            'data' => [
                'id' => $book->id,
                'title' => $bookRecord['title'],
                'author' => $bookRecord['author'],
                'isbn' => $bookRecord['isbn'],
                'published_date' => $bookRecord['published_date'],
                'image_url' => $bookRecord['image_url'],
                'description' => $bookRecord['description'],
                'genres' => $response->json('data.genres'),
                'reviews_avg_rating' => 0,
                'reviews_count' => 0,
                'reviews' => [],
            ],
        ]);

        // 5
        // sqliteによる日付の保存形式の違いを避け、日付として検証する
        $this->assertSame(
            $bookRecord['published_date'],
            $book->fresh()->published_date->toDateString(),
        );

        // booksのカラムにgenresはないので削除
        // published_dateは検証済みなので除外
        unset($bookRecord['genres'], $bookRecord['published_date']);

        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $this->user->id,
            ...$bookRecord,
        ]);

        $this->assertDatabaseCount('books', 1);

        // book_genreテーブルのリレーションを確認する
        foreach ([$novel, $essay] as $genre) {
            $this->assertDatabaseHas('book_genre', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }
        $this->assertDatabaseCount('book_genre', 2);
        $this->assertSame(2, $book->genres()->count());
    }

    /**
     * API書籍登録のtitleフィールドに不正な入力で実行したとき、登録に失敗する
     */
    public function testTitleValidation(): void {
        // 1. 有効な登録データを用意する
        // 2. タイトルを未入力・256文字・配列に変更してPOSTする
        // 3. HTTP 422とタイトルのエラーを確認し、DBが増えないことを確認する
        $this->assertInvalidBookCases('title', [
            ['', '書籍のタイトルを入力してください'],
            [str_repeat('あ', 256), '書籍のタイトルを255文字以内で入力してください'],
            [['不正な型'], null],
        ]);
    }

    /**
     * API書籍登録のauthorフィールドに不正な入力で実行したとき、登録に失敗する
     */
    public function testAuthorValidation(): void {
        // 1. 有効な登録データを用意する
        // 2. 著者名を未入力・256文字・配列に変更してPOSTする
        // 3. HTTP 422と著者名のエラーを確認し、DBが増えないことを確認する
        $this->assertInvalidBookCases('author', [
            ['', '著者名を入力してください'],
            [str_repeat('あ', 256), '著者名を255文字以内で入力してください'],
            [['不正な型'], null],
        ]);
    }

    /**
     * API書籍登録のisbnフィールドに不正な入力で実行したとき、登録に失敗する
     */
    public function testIsbnValidation(): void {
        // 1. 登録済みISBNの書籍を作成する
        // 2. ISBNを未入力・ハイフン入り・12桁・14桁・登録済みに変更してPOSTする
        // 3. HTTP 422とISBNのメッセージを確認し、既存件数が変わらないことを確認する
        $book = Book::factory()->create(['isbn' => '9781111111111']);
        $formatMessage = 'ISBNを13桁の数字で入力してください（ハイフン不要）';
        $this->assertInvalidBookCases('isbn', [
            ['', 'ISBNを入力してください'],
            ['978111111111-', $formatMessage],
            ['978000000000', $formatMessage],
            ['97800000000000', $formatMessage],
            [$book->isbn, 'このISBNは登録済みです'],
        ]);
    }

    /**
     * API書籍登録のpublished_dateフィールドに不正な入力で実行したとき、登録に失敗する
     */
    public function testPublishedDateValidation(): void {
        // 1. 出版日を形式不正の形式でPOSTする
        // 2. テスト: DBへの登録に失敗し、期待通りのエラーレスポンスを返す
        // 3. 出版日をありえない日付にしてPOSTする
        // 4. テスト: DBへの登録に失敗し、期待通りのエラーレスポンスを返す
        // 5. 基本機能からの変更点、出版日を未入力にしてPOSTする
        // 6. テスト: DBに登録される
        $this->assertInvalidBookCases('published_date', [
            ['26-1-1', '出版日をyyyy-mm-dd形式で入力してください'],
            ['2026-02-30', '出版日をyyyy-mm-dd形式で入力してください'],
        ]);

        // 登録前の書籍冊数を保持
        $bookCount = Book::count();

        // 出版日を未入力にしてpost
        $data = $this->validBookData();
        $data['published_date'] = '';
        $this->postJson('/api/v1/books', $data)
            ->assertSessionHasNoErrors();

        // DBの新規登録確認をする
        $this->assertDatabaseCount('books', $bookCount + 1);

        // 登録した書籍の出版日がnullで格納されていることを確認
        $newBook = Book::where('isbn', $data['isbn'])->firstOrFail();
        $this->assertSame($newBook->published_date, null);
    }

    /**
     * API書籍登録のgenresフィールドに不正な入力で実行したとき、登録に失敗する
     */
    public function testGenresValidation(): void {
        // 1. 登録済みジャンルを含む有効なデータを用意する
        // 2. ジャンルを空配列・文字列・数値要素・未登録名に変更してPOSTする
        // 3. HTTP 422と該当キーのエラーを確認し、書籍と紐付けが増えないことを確認する
        $this->assertInvalidBookCases('genres', [
            [[], 'genresを入力してください'],
            ['小説', 'genresは配列形式で入力してください'],
            [[123], 'ジャンルは文字列で入力してください', 'genres.0'],
            [['未登録のジャンル'], '登録済みのジャンルを入力して下さい', 'genres.0'],
        ]);
    }

    /**
     * API書籍登録のimage_urlフィールドに不正な入力で実行したとき、登録に失敗する
     */
    public function testImageUrlValidation(): void {
        // 1. 有効な登録データを用意する
        // 2. 画像URLを形式不正・2049文字に変更してPOSTする
        // 3. HTTP 422と画像URLのメッセージを確認し、DBが増えないことを確認する
        $prefix = 'https://example.com/';
        $this->assertInvalidBookCases('image_url', [
            ['https:/test', 'URL形式で入力してください'],
            [$prefix . str_repeat('a', 2049 - strlen($prefix)), 'URLは2048文字以内で入力してください'],
        ]);
    }

    /**
     * API書籍登録のdescriptionフィールドに不正な入力で実行したとき、登録に失敗する
     */
    public function testDescriptionValidation(): void {
        // 1. 有効な登録データを用意する
        // 2. 説明に配列を指定してPOSTする
        // 3. HTTP 422と説明のエラーを確認し、DBが増えないことを確認する
        $this->assertInvalidBookCases('description', [[['不正な型'], null]]);
    }

    /**
     * API書籍登録に有効な登録データを作成する
     */
    private function validBookData(): array {
        $genre = Genre::firstOrCreate(['name' => '小説']);

        return [
            'user_id' => $this->user->id,
            'title' => 'テスト本',
            'author' => 'テスト著者',
            'isbn' => '9780000000000',
            'published_date' => '2026-01-01',
            'genres' => [$genre->name],
            'image_url' => 'http://placehold.co/200x300/e2e8f0/475569?text=1',
            'description' => 'テスト本',
        ];
    }

    /**
     * 対象項目だけを書き換え、エラーとDB件数を検証する
     *
     * @param  string  $field  対象となる入力フィールド
     * @param  array  $cases  フィールドへの不正入力値と期待するバリデーションメッセージの組み合わせ配列
     */
    private function assertInvalidBookCases(string $field, array $cases): void {
        $data = $this->validBookData();
        $bookCount = Book::count();
        $pivotCount = DB::table('book_genre')->count();

        // 認証する
        Sanctum::actingAs($this->user, ['*']);

        foreach ($cases as $case) {
            [$value, $message] = $case;
            $errorKey = $case[2] ?? $field;
            $requestData = array_replace($data, [$field => $value]);
            if ($case[3] ?? false) {
                unset($requestData[$field]);
            }
            $response = $this->postJson('/api/v1/books', $requestData)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$errorKey]);

            // ネストしたジャンルのエラー以外に、無関係な項目のエラーがないことも確認
            $this->assertSame([$errorKey], array_keys($response->json('errors')));
            if ($message !== null) {
                $this->assertContains($message, $response->json('errors')[$errorKey]);
            }
            $this->assertDatabaseCount('books', $bookCount);
            $this->assertDatabaseCount('book_genre', $pivotCount);
        }
    }
}
