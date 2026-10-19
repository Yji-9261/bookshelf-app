<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EditBookTest extends TestCase
{
    use RefreshDatabase;

    /** API書籍更新を実行し期待通りの結果となる */
    public function testBooksUpdate(): void
    {
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
        unset($data['genres']);
        $this->assertDatabaseHas('books', ['id' => $book->id, ...$data]);
        $this->assertDatabaseCount('books', $bookCount);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $newGenre->id]);
        $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $oldGenre->id]);
        $this->assertSame(1, $book->genres()->count());
    }

    /** 存在しない書籍のAPI書籍更新を実行した時、期待通りのエラーレスポンスが返ってくる */
    public function testBookNotFound(): void
    {
        // 1. 1件の書籍を作成する
        // 2. PUT /api/v1/books/2にアクセス
        // 3. テスト: statuscode: 404 {'error' => '書籍情報が見つかりませんでした。'}が返ってくるか

        Book::factory()->create()
            ->genres()
            ->sync(Genre::create(['name' => '小説'])->id);

        $this->putJson('/api/v1/books/2')
            ->assertStatus(404)
            ->assertJson([
                'error' => '書籍情報が見つかりませんでした。',
            ]);
    }

    /** タイトルの不正入力を拒否する */
    public function testTitleValidation(): void
    {
        // 1. 有効な更新データを用意する
        // 2. タイトルを未入力・256文字・配列に変更してPUTする
        // 3. HTTP 422とタイトルのエラーを確認し、既存データが変更されないことを確認する
        $this->assertInvalidBookCases('title', [
            ['', '書籍のタイトルを入力してください'],
            [str_repeat('あ', 256), '書籍のタイトルを255文字以内で入力してください'],
            [['不正な型'], null],
        ]);
    }

    /** 著者名の不正入力を拒否する */
    public function testAuthorValidation(): void
    {
        // 1. 有効な更新データを用意する
        // 2. 著者名を未入力・256文字・配列に変更してPUTする
        // 3. HTTP 422と著者名のエラーを確認し、既存データが変更されないことを確認する
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
        // 2. ISBNを未入力・ハイフン入り・12桁・14桁・登録済みに変更してPUTする
        // 3. HTTP 422とISBNのメッセージを確認し、既存データが変更されないことを確認する
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
        // 1. 有効な更新データを用意する
        // 2. 出版日を未入力・形式不正・存在しない日付に変更してPUTする
        // 3. HTTP 422と出版日のメッセージを確認し、既存データが変更されないことを確認する
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
        // 2. ジャンルを空配列・文字列・数値要素・未登録名に変更してPUTする
        // 3. HTTP 422と該当キーのエラーを確認し、書籍と紐付けが変更されないことを確認する
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
        // 1. 有効な更新データを用意する
        // 2. 画像URLを形式不正・2049文字に変更してPUTする
        // 3. HTTP 422と画像URLのメッセージを確認し、既存データが変更されないことを確認する
        $prefix = 'https://example.com/';
        $this->assertInvalidBookCases('image_url', [
            ['https:/test', 'URL形式で入力してください'],
            [$prefix . str_repeat('a', 2049 - strlen($prefix)), 'URLは2048文字以内で入力してください'],
        ]);
    }

    /** 説明の型不正を拒否する */
    public function testDescriptionValidation(): void
    {
        // 1. 有効な更新データを用意する
        // 2. 説明に配列を指定してPUTする
        // 3. HTTP 422と説明のエラーを確認し、既存データが変更されないことを確認する
        $this->assertInvalidBookCases('description', [[['不正な型'], null]]);
    }

    /** ユーザーIDの型不正・未登録IDを拒否する */
    public function testUserIdValidation(): void
    {
        // 1. ユーザーを1人作成した有効なデータを用意する
        // 2. ユーザーIDを省略・null・空文字・非数値・存在しないIDにしてPUTする
        // 3. HTTP 422とユーザーIDのメッセージを確認し、既存データが変更されないことを確認する
        $this->assertInvalidBookCases('user_id', [
            [null, 'ユーザーIDを入力してください', 'user_id', true], // キーを省略
            [null, 'ユーザーIDを入力してください'],
            ['', 'ユーザーIDを入力してください'],
            ['abc', 'ユーザーIDを数値で入力してください'],
            [999999, '登録済みのユーザーを入力してください'],
        ]);
    }

    /** 更新対象と、他の項目が有効な更新データを作成する */
    private function validBookData(): array
    {
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

    /** 不正項目のエラーと、既存書籍・中間レコードの維持を確認する */
    private function assertInvalidBookCases(string $field, array $cases): void
    {
        $data = $this->validBookData();
        $book = Book::where('isbn', '9782222222222')->firstOrFail();
        $original = $book->getAttributes();
        $bookCount = Book::count();
        $originalPivots = DB::table('book_genre')->orderBy('id')->get()->toArray();

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
}
