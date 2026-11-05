<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EditBookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ゲストは書籍の更新ができない
     */
    public function testGuestCannotBooksUpdate(): void {
        // 1. ユーザー・書籍を紐つけ作成
        // 2. 認証せずに有効なデータをPUT /api/v1/books/{book}へ送信する
        // 3. テスト: 401エラーが発生する
        // 4. テスト: DB内の書籍が変更されていない

        // 1
        $postData = $this->validBookData();
        $book = Book::where('isbn', '9782222222222')->firstOrFail();
        $original = $book->getAttributes();

        // 2,3
        $this->putJson('/api/v1/books/' . $book->id, $postData)
            ->assertUnauthorized();

        // 4
        $this->assertSame($original, $book->refresh()->getAttributes());
    }

    /**
     * 書籍登録ユーザーがAPI書籍更新を実行し、更新に成功する
     */
    public function testBooksUpdate(): void {
        // 1. ユーザー・書籍・変更前後のジャンルを作成する
        // 2. 各項目とジャンルを変更した有効なデータをPUT /api/v1/books/{book}へ送信する
        // 3. テスト: HTTP 200で更新後の書籍詳細と同じ形式のJSONが返る
        // 4. テスト: 同じIDの書籍が更新され、ジャンルが置換され、書籍件数が増えない

        // 1
        $user = User::factory()->create();
        $book = Book::factory()->create(['user_id' => $user->id, 'title' => '更新前の本']);
        $oldGenre = Genre::create(['name' => '小説']);
        $newGenre = Genre::create(['name' => 'エッセイ']);
        $book->genres()->attach($oldGenre->id);
        $bookCount = Book::count();

        // 2,3
        $data = [
            'user_id' => $user->id,
            'title' => '更新した本',
            'author' => '更新した著者',
            'isbn' => '9780000000000',
            'published_date' => '2026-01-01',
            'image_url' => 'https://example.com/updated.jpg',
            'description' => '更新した説明',
            'genres' => [$newGenre->name],
        ];

        // 認証し書籍更新実行
        Sanctum::actingAs($user, ['*']);
        $this->putJson('/api/v1/books/' . $book->id, $data)
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'id' => $book->id,
                    'title' => $data['title'],
                    'author' => $data['author'],
                    'isbn' => $data['isbn'],
                    'published_date' => $data['published_date'],
                    'image_url' => $data['image_url'],
                    'description' => $data['description'],
                    'genres' => [['id' => $newGenre->id, 'name' => $newGenre->name]],
                    'reviews_avg_rating' => 0,
                    'reviews_count' => 0,
                    'reviews' => [],
                ],
            ]);

        // 4
        // 日付検証
        $this->comparePublishedDate($book->refresh(), $data['published_date']);

        // ジャンルと日付はそのままではデータベース比較できないので除外し別個で確認
        unset($data['genres'], $data['published_date']);
        $this->assertDatabaseHas('books', ['id' => $book->id, ...$data]);
        $this->assertDatabaseCount('books', $bookCount);

        // ジャンル比較
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $newGenre->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $oldGenre->id]);
        $this->assertSame(1, $book->genres()->count());
    }

    /**
     * 存在しない書籍に対してAPI書籍更新を実行した時、期待通りのエラーレスポンスが返ってくる
     */
    public function testBookNotFound(): void {
        // 1. 1件の書籍を作成する
        // 2. PUT /api/v1/books/2にアクセス
        // 3. テスト: statuscode: 404 {'error' => '書籍情報が見つかりませんでした。'}が返ってくるか

        $book = Book::factory()->create();
        $book->genres()->sync(Genre::create(['name' => '小説'])->id);

        Sanctum::actingAs($book->registerUser, ['*']);
        $this->putJson('/api/v1/books/2')
            ->assertStatus(404)
            ->assertJson([
                'error' => '書籍情報が見つかりませんでした。',
            ]);
    }

    /**
     * titleフィールドに不正入力されたとき、期待するエラーレスポンスを返し、更新に失敗する
     */
    public function testTitleValidation(): void {
        // 1. 有効な更新データを用意する
        // 2. タイトルを未入力・256文字・配列に変更してPUTする
        // 3. テスト: HTTP 422とタイトルのエラーを確認し、既存データが変更されないことを確認する
        $this->assertInvalidBookCases('title', [
            ['', '書籍のタイトルを入力してください'],
            [str_repeat('あ', 256), '書籍のタイトルを255文字以内で入力してください'],
            [['不正な型'], null],
        ]);
    }

    /**
     * authorフィールドに不正入力されたとき、期待するエラーレスポンスを返し、更新に失敗する
     */
    public function testAuthorValidation(): void {
        // 1. 有効な更新データを用意する
        // 2. 著者名を未入力・256文字・配列に変更してPUTする
        // 3. テスト: HTTP 422と著者名のエラーを確認し、既存データが変更されないことを確認する
        $this->assertInvalidBookCases('author', [
            ['', '著者名を入力してください'],
            [str_repeat('あ', 256), '著者名を255文字以内で入力してください'],
            [['不正な型'], null],
        ]);
    }

    /**
     * ISBNフィールドに不正入力されたとき、期待するエラーレスポンスを返し、更新に失敗する
     */
    public function testIsbnValidation(): void {
        // 1. 登録済みISBNの書籍を作成する
        // 2. ISBNを未入力・ハイフン入り・12桁・14桁・登録済みに変更してPUTする
        // 3. テスト: HTTP 422とISBNのメッセージを確認し、既存データが変更されないことを確認する
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
     * published_dateフィールドに不正入力されたとき、期待するエラーレスポンスを返し、更新に失敗する
     */
    public function testPublishedDateValidation(): void {
        // 1. 有効な更新データを用意する
        // 2. 出版日を形式不正・ありえない日付に変更してPUTする
        // 3. HTTP 422と出版日のメッセージを確認し、既存データが変更されないことを確認する
        $this->assertInvalidBookCases('published_date', [
            ['26-1-1', '出版日をyyyy-mm-dd形式で入力してください'],
            ['2026-02-30', '出版日をyyyy-mm-dd形式で入力してください'],
        ]);
    }

    /**
     * published_dateフィールドの未入力は許容する
     * 応用機能変更時のテスト
     */
    public function testPublishedDateNullable(): void {
        // 1. 有効な書籍を登録する
        // 2. 出版日を未入力にしAPI書籍更新メソッドを実行
        // 3. テスト: 出版日を未入力で書籍が更新される

        // 1
        $postData = $this->validBookData();

        // 2
        $postData['published_date'] = '';
        $book = Book::where('isbn', '9782222222222')->firstOrFail();
        Sanctum::actingAs($book->registerUser, ['*']);
        $this->putJson('/api/v1/books/' . $book->id, $postData)
            ->assertOk();

        // 3
        $this->assertNull($book->refresh()->published_date);
    }

    /**
     * genresフィールドに不正入力されたとき、期待するエラーレスポンスを返し、更新に失敗する
     */
    public function testGenresValidation(): void {
        // 1. 登録済みジャンルを含む有効なデータを用意する
        // 2. ジャンルを空配列・文字列・数値要素・未登録名に変更してPUTする
        // 3. テスト: HTTP 422と該当キーのエラーを確認し、書籍と紐付けが変更されないことを確認する
        $this->assertInvalidBookCases('genres', [
            [[], 'genresを入力してください'],
            ['小説', 'genresは配列形式で入力してください'],
            [[123], 'ジャンルは文字列で入力してください', 'genres.0'],
            [['未登録のジャンル'], '登録済みのジャンルを入力して下さい', 'genres.0'],
        ]);
    }

    /**
     * image_urlフィールドに不正入力されたとき、期待するエラーレスポンスを返し、更新に失敗する
     */
    public function testImageUrlValidation(): void {
        // 1. 有効な更新データを用意する
        // 2. 画像URLを形式不正・2049文字に変更してPUTする
        // 3. テスト: HTTP 422と画像URLのメッセージを確認し、既存データが変更されないことを確認する
        $prefix = 'https://example.com/';
        $this->assertInvalidBookCases('image_url', [
            ['https:/test', 'URL形式で入力してください'],
            [$prefix . str_repeat('a', 2049 - strlen($prefix)), 'URLは2048文字以内で入力してください'],
        ]);
    }

    /**
     * descriptionフィールドに不正入力されたとき、期待するエラーレスポンスを返し、更新に失敗する
     */
    public function testDescriptionValidation(): void {
        // 1. 有効な更新データを用意する
        // 2. 説明に配列を指定してPUTする
        // 3. テスト: HTTP 422と説明のエラーを確認し、既存データが変更されないことを確認する
        $this->assertInvalidBookCases('description', [[['不正な型'], null]]);
    }

    /**
     * 書籍登録したユーザー以外は書籍の更新ができない
     */
    public function testCannotUpdateOthersBook(): void {
        // 1. ユーザー・書籍を紐つけ作成
        // 2. 書籍登録していないユーザーを作成
        // 3. 書籍登録していないユーザーで認証し、有効なデータをPUT /api/v1/books/{book}へ送信する
        // 4. テスト: 403エラーが発生する
        // 5. テスト: DB内の書籍が変更されていない

        // 1
        $postData = $this->validBookData();
        $book = Book::where('isbn', '9782222222222')->firstOrFail();
        $original = $book->getAttributes();

        // 2~4
        Sanctum::actingAs(User::factory()->create(), ['*']);
        $this->putJson('/api/v1/books/' . $book->id, $postData)
            ->assertForbidden();

        // 5
        $this->assertSame($original, $book->refresh()->getAttributes());
    }

    /**
     * 書籍を作成し、更新に有効なデータを作成し返す
     */
    private function validBookData(): array {
        $user = User::factory()->create();
        $oldGenre = Genre::firstOrCreate(['name' => '小説']);
        $newGenre = Genre::firstOrCreate(['name' => 'エッセイ']);
        $book = Book::factory()->create([
            'user_id' => $user->id,
            'title' => '更新前の本',
            'isbn' => '9782222222222',
        ]);
        $book->genres()->attach($oldGenre->id);

        return [
            'user_id' => $user->id,
            'title' => '更新後の本',
            'author' => '更新後の著者',
            'isbn' => '9780000000000',
            'published_date' => '2026-01-01',
            'genres' => [$newGenre->name],
            'image_url' => 'https://example.com/updated.jpg',
            'description' => '更新後の説明',
        ];
    }

    /**
     * 不正項目のエラーと、既存書籍・中間レコードの維持を確認する
     *
     * @param  string  $field  対象となる入力フィールド
     * @param  array  $cases  フィールドへの不正入力値と期待するバリデーションメッセージの組み合わせ配列
     */
    private function assertInvalidBookCases(string $field, array $cases): void {
        $data = $this->validBookData();
        $book = Book::where('isbn', '9782222222222')->firstOrFail();
        $original = $book->getAttributes();
        $bookCount = Book::count();
        $originalPivots = DB::table('book_genre')->orderBy('id')->get()->toArray();

        Sanctum::actingAs($book->registerUser, ['*']);
        foreach ($cases as $case) {
            [$value, $message] = $case;
            $errorKey = $case[2] ?? $field;
            $requestData = array_replace($data, [$field => $value]);
            if ($case[3] ?? false) {
                unset($requestData[$field]);
            }
            $response = $this->putJson('/api/v1/books/' . $book->id, $requestData)
                ->assertUnprocessable()
                ->assertJsonValidationErrors([$errorKey]);

            $this->assertSame([$errorKey], array_keys($response->json('errors')));
            if ($message !== null) {
                $this->assertContains($message, $response->json('errors')[$errorKey]);
            }
            $this->assertSame($original, $book->fresh()->getAttributes());
            $this->assertDatabaseCount('books', $bookCount);
            $this->assertEquals($originalPivots, DB::table('book_genre')->orderBy('id')->get()->toArray());
        }
    }

    /**
     * データベース内の日付を比較する(sqliteはdatetime式、mysqlとmodelではdateで扱っている)
     */
    private function comparePublishedDate(Book $editedBook, string $compareDateString): void {
        $this->assertSame($editedBook->published_date->toDateString(), $compareDateString);
    }
}
