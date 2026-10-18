<?php

namespace Tests\Feature\BookManager;

use App\Models\User;
use App\Models\Book;
use App\Models\Genre;
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

class EditBookTest extends TestCase
{
    use RefreshDatabase;

    const TITLE = 'テスト本';
    const AUTHOR = 'テスト';
    const ISBN = '9780000000000';
    const PUBLISHED_DATE = '2026-01-01';
    const GENRE_NAMES = ['小説'];

    private function putUpdateBook(
        Book $book,
        $title = self::TITLE,
        $author = self::AUTHOR,
        $isbn = self::ISBN,
        $publishDate = self::PUBLISHED_DATE,
        $genreNames = self::GENRE_NAMES,
        $imageUrl = '',
        $description = '',
    ) {
        // テストはわかりやすいように名称選択としている
        // PutデータにはIDが必要なのでID配列に変換
        $ids = Genre::whereIn('name', $genreNames)
            ->pluck('id')
            ->toArray();

        return $this->actingAs(User::findOrFail($book->user_id))
            ->put(route('books.update', $book), [
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'published_date' => $publishDate,
                'genres' => $ids,
                'image_url' => $imageUrl,
                'description' => $description,
            ]);
    }

    /** 書籍編集画面が表示される */
    public function testEditView(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. 対象書籍の登録者として認証し、書籍編集画面にアクセス
        // 3. テスト: タイトル・著者・ISBN・出版日・説明・画像URLに対象書籍の初期値が表示される
        // 4. テスト: 全ジャンルが表示され、対象書籍のジャンルだけが選択されている

        // 1
        $this->createTestDataFromSeeder();
        $user = User::firstOrFail();
        $book = $user->registeredBooks()->with(['genres', 'reviews.user'])->firstOrFail();

        // 2
        $response = $this->actingAs($user)
            ->get(route('books.edit', $book))
            ->assertOk()
            ->assertViewIs('books.edit')
            ->assertViewHas('book', fn(Book $shownBook) => $shownBook->is($book));

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@action="' . route('books.update', $book) . '"]');
        $this->assertCount(1, $forms);
        $form = $forms->item(0);

        foreach (['title', 'author', 'isbn', 'published_date', 'image_url'] as $field) {
            $inputs = $xpath->query('.//input[@name="' . $field . '"]', $form);
            $this->assertCount(1, $inputs);
            $this->assertSame((string) $book->$field, $inputs->item(0)->getAttribute('value'));
        }
        $descriptions = $xpath->query('.//textarea[@name="description"]', $form);
        $this->assertCount(1, $descriptions);
        $this->assertSame((string) $book->description, $descriptions->item(0)->textContent);

        // 4
        $genres = Genre::all();
        $this->assertNotEmpty($genres);
        $this->assertNotEmpty($book->genres);
        $this->assertCount($genres->count(), $xpath->query('.//input[@type="checkbox" and @name="genres[]"]', $form));
        foreach ($genres as $genre) {
            $checkboxes = $xpath->query('.//input[@type="checkbox" and @name="genres[]" and @value="' . $genre->id . '"]', $form);
            $this->assertCount(1, $checkboxes);
            $checkbox = $checkboxes->item(0);
            $this->assertSame($genre->name, $xpath->evaluate('normalize-space(parent::label/span)', $checkbox));
            $this->assertSame($book->genres->contains($genre->id), $checkbox->hasAttribute('checked'));
        }
    }

    /** 正常な入力をして「更新」ボタンを押下する */
    public function testUpdateBook(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. 書籍登録者として認証し、postEditBook()で書籍更新
        // 3. テスト: 書籍詳細画面にリダイレクトされる
        // 4. テスト: タイトル・著者・ISBN・出版日・説明・画像URL、ジャンルに対象書籍の編集内容が反映されている

        // 1
        $this->createTestDataFromSeeder();
        $book = Book::findOrFail(1);

        // 検証データ
        $bookCount = Book::count();
        $genre = Genre::where('name', '技術書')->firstOrFail();
        $ownerId = $book->user_id;
        $oldGenreIds = $book->genres()->pluck('genres.id');
        $data = [
            'title' => '更新後のテスト本',
            'author' => '更新後の著者',
            'isbn' => self::ISBN,
            'published_date' => self::PUBLISHED_DATE,
            'image_url' => 'https://example.com/updated.jpg',
            'description' => '更新後の書籍説明です。',
        ];

        // 2,3
        $this->putUpdateBook(
            book: $book,
            title: $data['title'],
            author: $data['author'],
            isbn: $data['isbn'],
            publishDate: $data['published_date'],
            genreNames: [$genre->name],
            imageUrl: $data['image_url'],
            description: $data['description'],
        )->assertSessionHasNoErrors()
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍情報を更新しました。');

        // 4
        // DB 登録テスト
        $this->assertDatabaseHas('books', [
            'id' => $book->id,
            'user_id' => $ownerId,
            ...$data,
        ]);
        $this->assertDatabaseCount('books', $bookCount);
        $this->assertDatabaseHas('book_genre', ['book_id' => $book->id, 'genre_id' => $genre->id]);
        foreach ($oldGenreIds as $oldGenreId) {
            $this->assertDatabaseMissing('book_genre', ['book_id' => $book->id, 'genre_id' => $oldGenreId]);
        }
        // 実表示テスト
        $response = $this->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertSeeText('書籍情報を更新しました。')
            ->assertSeeText($genre->name);
        foreach (['title', 'author', 'isbn', 'published_date', 'description'] as $field) {
            $response->assertSeeText($data[$field]);
        }
        $response->assertSee('src="' . $data['image_url'] . '"', false);
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(title編) */
    public function testTitleValidation(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. 書籍登録ユーザーとして認証し、titleを空にしてputアクセス
        // 3. テスト: 編集画面にリダイレクトされ、「書籍のタイトルを入力してください」が表示され、DBの内容が更新されていない
        // 4. titleを255文字超でputアクセス
        // 5. テスト: 編集画面に戻り、「書籍のタイトルを255文字以内で入力してください」が表示され、DBの内容が更新されていない

        // 1
        $this->createTestDataFromSeeder();
        $book = Book::findOrFail(1);
        $original = $book->getAttributes();
        $originalGenreIds = $book->genres()->orderBy('genres.id')->pluck('genres.id')->all();
        $bookCount = Book::count();
        $editUrl = route('books.edit', $book);
        $cases = [
            ['', '書籍のタイトルを入力してください'],
            [str_repeat('あ', 256), '書籍のタイトルを255文字以内で入力してください'],
        ];

        // 2~5
        foreach ($cases as [$title, $message]) {
            $this->from($editUrl)->putUpdateBook(book: $book, title: $title)
                ->assertRedirect($editUrl)
                ->assertSessionHasErrors(['title' => $message])
                ->assertSessionDoesntHaveErrors(['author', 'isbn', 'published_date', 'genres', 'image_url', 'description']);

            $this->get($editUrl)
                ->assertOk()
                ->assertViewIs('books.edit')
                ->assertSeeText($message);

            $this->assertSame($original, $book->fresh()->getAttributes());
            $this->assertSame($originalGenreIds, $book->genres()->orderBy('genres.id')->pluck('genres.id')->all());
            $this->assertDatabaseCount('books', $bookCount);
        }
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(author編) */
    public function testAuthorValidation(): void
    {
        // 1. テストデータを作成する
        // 2. 書籍登録者として認証し、authorを空にしてputアクセス
        // 3. テスト: 編集画面に戻り、「著者名を入力してください」が表示され、DBの内容が更新されていない
        // 4. authorを255文字超でputアクセス
        // 5. テスト: 編集画面に戻り、「著者名を255文字以内で入力してください」が表示され、DBの内容が更新されていない

        // 1
        $this->createTestDataFromSeeder();
        $book = Book::findOrFail(1);
        $original = $book->getAttributes();
        $originalGenreIds = $book->genres()->orderBy('genres.id')->pluck('genres.id')->all();
        $bookCount = Book::count();
        $editUrl = route('books.edit', $book);
        $cases = [
            ['', '著者名を入力してください'],
            [str_repeat('あ', 256), '著者名を255文字以内で入力してください'],
        ];

        // 2~5
        foreach ($cases as [$author, $message]) {
            $this->from($editUrl)->putUpdateBook(book: $book, author: $author)
                ->assertRedirect($editUrl)
                ->assertSessionHasErrors(['author' => $message])
                ->assertSessionDoesntHaveErrors(['title', 'isbn', 'published_date', 'genres', 'image_url', 'description']);

            $this->get($editUrl)
                ->assertOk()
                ->assertViewIs('books.edit')
                ->assertSeeText($message);

            // DBの内容が更新されていないことを確認
            $this->assertSame($original, $book->fresh()->getAttributes());
            $this->assertSame($originalGenreIds, $book->genres()->orderBy('genres.id')->pluck('genres.id')->all());
            $this->assertDatabaseCount('books', $bookCount);
        }
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(isbn編) */
    public function testIsbnValidation(): void
    {
        // 1. テストデータを作成する
        // 2. 書籍登録者として認証し、isbnを空にしてPUT送信
        // 3. テスト: 編集画面に戻り、「ISBNを入力してください」が表示され、DBの内容が更新されていない
        // 4. ISBNを'978111111111-'にしてPUT送信
        // 5. テスト: 編集画面に戻り、「ISBNを13桁の数字で入力してください（ハイフン不要）」が表示され、DBの内容が更新されていない
        // 6. ISBNを別の書籍の登録済みISBNにしてPUT送信
        // 7. テスト: 編集画面に戻り、「このISBNは登録済みです」が表示され、DBの内容が更新されていない

        // 1
        $this->createTestDataFromSeeder();
        $book = Book::findOrFail(1);

        // 検証データ
        $original = $book->getAttributes();
        $originalGenreIds = $book->genres()->orderBy('genres.id')->pluck('genres.id')->all();
        $bookCount = Book::count();
        $editUrl = route('books.edit', $book);

        // Ruleの中で編集中の書籍をignore設定しているのでそれ以外の書籍のISBNを使用したい
        $otherBook = Book::whereKeyNot($book->id)->firstOrFail();
        $cases = [
            ['', 'ISBNを入力してください'],
            ['978111111111-', 'ISBNを13桁の数字で入力してください（ハイフン不要）'],
            [$otherBook->isbn, 'このISBNは登録済みです'],
        ];

        // 2~7
        foreach ($cases as [$isbn, $message]) {
            $this->from($editUrl)->putUpdateBook(book: $book, isbn: $isbn)
                ->assertRedirect($editUrl)
                ->assertSessionHasErrors(['isbn' => $message])
                ->assertSessionDoesntHaveErrors(['title', 'author', 'published_date', 'genres', 'image_url', 'description']);

            $this->get($editUrl)
                ->assertOk()
                ->assertViewIs('books.edit')
                ->assertSeeText($message);

            // DBの内容が更新されていないことを確認
            $this->assertSame($original, $book->fresh()->getAttributes());
            $this->assertSame($originalGenreIds, $book->genres()->orderBy('genres.id')->pluck('genres.id')->all());
            $this->assertDatabaseCount('books', $bookCount);
        }
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(published_date編) */
    public function testPublishedDateValidation(): void
    {
        // 1. テストデータを作成する
        // 2. 書籍登録者として認証し、published_dateを空にしてPUT送信
        // 3. テスト: 編集画面に戻り、「出版日を入力してください」が表示され、DBの内容が更新されていない

        // 1
        $this->createTestDataFromSeeder();
        $book = Book::findOrFail(1);

        // 検証データ
        $original = $book->getAttributes();
        $originalGenreIds = $book->genres()->orderBy('genres.id')->pluck('genres.id')->all();
        $bookCount = Book::count();
        $editUrl = route('books.edit', $book);

        // 2,3
        $this->from($editUrl)->putUpdateBook(book: $book, publishDate: '')
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors(['published_date' => '出版日を入力してください'])
            ->assertSessionDoesntHaveErrors(['title', 'author', 'isbn', 'genres', 'image_url', 'description']);

        $this->get($editUrl)
            ->assertOk()
            ->assertViewIs('books.edit')
            ->assertSeeText('出版日を入力してください');

        $this->assertSame($original, $book->fresh()->getAttributes());
        $this->assertSame($originalGenreIds, $book->genres()->orderBy('genres.id')->pluck('genres.id')->all());
        $this->assertDatabaseCount('books', $bookCount);
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(genres編) */
    public function testGenresValidation(): void
    {
        // 1. テストデータを作成する
        // 2. 書籍登録者として認証し、genresを空にしてPUT送信
        // 3. テスト: 編集画面に戻り、「ジャンルを選択してください」が表示され、書籍とジャンルの紐付けが更新されていない

        // 1
        $this->createTestDataFromSeeder();
        $book = Book::findOrFail(1);

        // 検証データ
        $original = $book->getAttributes();
        $originalGenreIds = $book->genres()->orderBy('genres.id')->pluck('genres.id')->all();
        $bookCount = Book::count();
        $editUrl = route('books.edit', $book);

        // 2~3
        $this->from($editUrl)->putUpdateBook(book: $book, genreNames: [])
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors(['genres' => 'ジャンルを選択してください'])
            ->assertSessionDoesntHaveErrors(['title', 'author', 'isbn', 'published_date', 'image_url', 'description']);

        $this->get($editUrl)
            ->assertOk()
            ->assertViewIs('books.edit')
            ->assertSeeText('ジャンルを選択してください');

        $this->assertSame($original, $book->fresh()->getAttributes());
        $this->assertSame($originalGenreIds, $book->genres()->orderBy('genres.id')->pluck('genres.id')->all());
        $this->assertDatabaseCount('books', $bookCount);
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(image_url編) */
    public function testImageUrlValidation(): void
    {
        // 1. テストデータを作成する
        // 2. 書籍登録者として認証し、image_urlを'https:/test'にしてPUT送信
        // 3. テスト: 編集画面に戻り、「URL形式で入力してください」が表示され、DBの内容が更新されていない
        // 4. image_urlをURL形式で2049文字にしてPUT送信
        // 5. テスト: 編集画面に戻り、「URLは2048文字以内で入力してください」が表示され、DBの内容が更新されていない

        // 1
        $this->createTestDataFromSeeder();
        $book = Book::findOrFail(1);

        // 検証データ
        $original = $book->getAttributes();
        $originalGenreIds = $book->genres()->orderBy('genres.id')->pluck('genres.id')->all();
        $bookCount = Book::count();
        $editUrl = route('books.edit', $book);
        $urlPrefix = 'https://example.com/';
        $cases = [
            ['https:/test', 'URL形式で入力してください'],
            [$urlPrefix . str_repeat('a', 2049 - strlen($urlPrefix)), 'URLは2048文字以内で入力してください'],
        ];

        // 2~5
        foreach ($cases as [$imageUrl, $message]) {
            $this->from($editUrl)->putUpdateBook(book: $book, imageUrl: $imageUrl)
                ->assertRedirect($editUrl)
                ->assertSessionHasErrors(['image_url' => $message])
                ->assertSessionDoesntHaveErrors(['title', 'author', 'isbn', 'published_date', 'genres', 'description']);

            $this->get($editUrl)
                ->assertOk()
                ->assertViewIs('books.edit')
                ->assertSeeText($message);

            $this->assertSame($original, $book->fresh()->getAttributes());
            $this->assertSame($originalGenreIds, $book->genres()->orderBy('genres.id')->pluck('genres.id')->all());
            $this->assertDatabaseCount('books', $bookCount);
        }
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
