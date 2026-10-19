<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CreateBookTest extends TestCase
{
    use RefreshDatabase;

    /** API書籍登録を実行し期待通りの結果となる */
    public function testBooksCreate(): void
    {
        // 1. validBookData()でユーザー・小説ジャンル・有効な登録データを作成する
        // 2. 作成日時の異なる「小説」「エッセイ」ジャンルを作成する
        // 3. 有効な書籍情報とジャンル名の配列をPOST /api/v1/booksへ送信する
        // 4. テスト: HTTP 201と、書籍情報・2ジャンル・平均評価0・レビュー数0が返る
        // 5. テスト: 書籍が1件保存され、指定した2ジャンルが紐付く

        // 1
        $bookRecord = $this->validBookData();

        // 2
        $novel = Genre::where('name', $bookRecord['genres'][0])->firstOrFail();
        $essay = Genre::create(['name' => 'エッセイ']);
        $bookRecord['genres'][] = $essay->name;

        // 3
        // 書籍テーブルが今はからであることを検証
        $this->assertDatabaseEmpty('books');
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
                'title' => $book->title,
                'author' => $book->author,
                'isbn' => $book->isbn,
                'published_date' => $book->published_date,
                'image_url' => $book->image_url,
                'description' => $book->description,
                'genres' => $response->json('data.genres'),
                'reviews_avg_rating' => 0,
                'reviews_count' => 0,
            ],
        ]);

        // 5
        // booksのカラムにgenresはないので削除
        unset($bookRecord['genres']);

        $this->assertDatabaseHas('books', ['id' => $book->id, ...$bookRecord]);
        $this->assertDatabaseCount('books', 1);

        //book_genreテーブルのリレーションを確認する
        foreach ([$novel, $essay] as $genre) {
            $this->assertDatabaseHas('book_genre', [
                'book_id' => $book->id,
                'genre_id' => $genre->id,
            ]);
        }
        $this->assertDatabaseCount('book_genre', 2);
        $this->assertSame(2, $book->genres()->count());
    }

    /** タイトルの不正入力を拒否する */
    public function testTitleValidation(): void
    {
        // 1. 有効な登録データを用意する
        // 2. タイトルを未入力・256文字・配列に変更してPOSTする
        // 3. HTTP 422とタイトルのエラーを確認し、DBが増えないことを確認する
        $this->assertInvalidBookCases('title', [
            ['', '書籍のタイトルを入力してください'],
            [str_repeat('あ', 256), '書籍のタイトルを255文字以内で入力してください'],
            [['不正な型'], null],
        ]);
    }

    /** 著者名の不正入力を拒否する */
    public function testAuthorValidation(): void
    {
        // 1. 有効な登録データを用意する
        // 2. 著者名を未入力・256文字・配列に変更してPOSTする
        // 3. HTTP 422と著者名のエラーを確認し、DBが増えないことを確認する
        $this->assertInvalidBookCases('author', [
            ['', '著者名を入力してください'],
            [str_repeat('あ', 256), '著者名を255文字以内で入力してください'],
            [['不正な型'], null],
        ]);
    }

    /** ISBNの未入力・形式不正・重複を拒否する */
    public function testIsbnValidation(): void
    {
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

    /** 出版日の未入力・形式不正・存在しない日付を拒否する */
    public function testPublishedDateValidation(): void
    {
        // 1. 有効な登録データを用意する
        // 2. 出版日を未入力・形式不正・存在しない日付に変更してPOSTする
        // 3. HTTP 422と出版日のメッセージを確認し、DBが増えないことを確認する
        $this->assertInvalidBookCases('published_date', [
            ['', '出版日を入力してください'],
            ['26-1-1', '出版日をyyyy-mm-dd形式で入力してください'],
            ['2026-02-30', '出版日をyyyy-mm-dd形式で入力してください'],
        ]);
    }

    /** ジャンルの未選択・型不正・未登録名を拒否する */
    public function testGenresValidation(): void
    {
        // 1. 登録済みジャンルを含む有効なデータを用意する
        // 2. ジャンルを空配列・文字列・数値要素・未登録名に変更してPOSTする
        // 3. HTTP 422と該当キーのエラーを確認し、書籍と紐付けが増えないことを確認する
        $this->assertInvalidBookCases('genres', [
            [[], 'ジャンルを選択してください'],
            ['小説', null],
            [[123], 'ジャンルは文字列で入力してください', 'genres.0'],
            [['未登録のジャンル'], '登録済みのジャンルを入力して下さい', 'genres.0'],
        ]);
    }

    /** 画像URLの形式不正と文字数超過を拒否する */
    public function testImageUrlValidation(): void
    {
        // 1. 有効な登録データを用意する
        // 2. 画像URLを形式不正・2049文字に変更してPOSTする
        // 3. HTTP 422と画像URLのメッセージを確認し、DBが増えないことを確認する
        $prefix = 'https://example.com/';
        $this->assertInvalidBookCases('image_url', [
            ['https:/test', 'URL形式で入力してください'],
            [$prefix . str_repeat('a', 2049 - strlen($prefix)), 'URLは2048文字以内で入力してください'],
        ]);
    }

    /** 説明の型不正を拒否する */
    public function testDescriptionValidation(): void
    {
        // 1. 有効な登録データを用意する
        // 2. 説明に配列を指定してPOSTする
        // 3. HTTP 422と説明のエラーを確認し、DBが増えないことを確認する
        $this->assertInvalidBookCases('description', [[['不正な型'], null]]);
    }

    /** ユーザーIDの型不正・未登録IDを拒否する */
    public function testUserIdValidation(): void
    {
        // 1. ユーザーを1人作成した有効なデータを用意する
        // 2. ユーザーIDを省略・null・空文字・非数値・存在しないIDにしてPOSTする
        // 3. HTTP 422とユーザーIDのメッセージを確認し、DBが増えないことを確認する
        $this->assertInvalidBookCases('user_id', [
            ['', 'ユーザーIDを入力してください'],
            ['abc', 'ユーザーIDを数値で入力してください'],
            [999999, '登録済みのユーザーを入力してください'],
        ]);
    }

    /** 他の項目が有効な登録データを作成する */
    private function validBookData(): array
    {
        $user = User::factory()->create();
        $genre = Genre::firstOrCreate(['name' => '小説']);

        return [
            'user_id' => $user->id,
            'title' => 'テスト本',
            'author' => 'テスト著者',
            'isbn' => '9780000000000',
            'published_date' => '2026-01-01',
            'genres' => [$genre->name],
            'image_url' => 'http://placehold.co/200x300/e2e8f0/475569?text=1',
            'description' => 'テスト本',
        ];
    }

    /** 対象項目だけを書き換え、エラーとDB件数を検証する */
    private function assertInvalidBookCases(string $field, array $cases): void
    {
        $data = $this->validBookData();
        $bookCount = Book::count();
        $pivotCount = DB::table('book_genre')->count();

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
