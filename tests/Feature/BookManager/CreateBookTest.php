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

class CreateBookTest extends TestCase
{
    use RefreshDatabase;

    const TITLE = 'テスト本';
    const AUTHOR = 'テスト';
    const ISBN = '9780000000000';
    const PUBLISHED_DATE = '2026-01-01';
    const GENRE_NAMES = ['小説'];

    private function postCreateBook(
        $title = self::TITLE,
        $author = self::AUTHOR,
        $isbn = self::ISBN,
        $publishDate = self::PUBLISHED_DATE,
        $genreNames = self::GENRE_NAMES,
        $imageUrl = '',
        $description = '',
    ) {
        // テストはわかりやすいように名称選択としている
        // PostデータにはIDが必要なのでID配列に変換
        $ids = Genre::whereIn('name', $genreNames)
            ->pluck('id')
            ->toArray();

        return $this->actingAs(User::findOrFail(1))
            ->post(route('books.store'), [
                'title' => $title,
                'author' => $author,
                'isbn' => $isbn,
                'published_date' => $publishDate,
                'genres' => $ids,
                'image_url' => $imageUrl,
                'description' => $description,
            ]);
    }

    /** 書籍登録画面が表示される */
    public function testCreateView(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. 認証ユーザーとして書籍登録画面にアクセス
        // 3. テスト: タイトル・著者・ISBN・出版日・説明・画像URLの入力欄が存在する
        // 4. テスト: 全ジャンル一覧のチェックボックスが表示される
        // 5. テスト: route('books.store')へPOST送信するフォームと登録ボタンが存在する

        // 1
        $this->createTestDataFromSeeder();

        // 2
        $this->actingAs(User::firstOrFail());
        $createUrl = route('books.create');
        $response = $this->get($createUrl)
            ->assertOk()
            ->assertViewIs('books.create');

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@action="' . route('books.store') . '"]');
        $this->assertCount(1, $forms);
        $form = $forms->item(0);

        foreach (['title', 'author', 'isbn', 'published_date', 'image_url'] as $field) {
            $this->assertCount(1, $xpath->query('.//input[@name="' . $field . '"]', $form));
        }
        $this->assertCount(1, $xpath->query('.//textarea[@name="description"]', $form));

        // 4
        $genres = Genre::all();
        $this->assertNotEmpty($genres);
        $this->assertCount($genres->count(), $xpath->query('.//input[@type="checkbox" and @name="genres[]"]', $form));
        foreach ($genres as $genre) {
            $checkbox = $xpath->query('.//input[@type="checkbox" and @name="genres[]" and @value="' . $genre->id . '"]', $form);
            $this->assertCount(1, $checkbox);
            $this->assertSame($genre->name, $xpath->evaluate('normalize-space(parent::label/span)', $checkbox->item(0)));
        }

        // 5
        $this->assertSame('POST', strtoupper($form->getAttribute('method')));
        $buttons = $xpath->query('.//button[@type="submit"]', $form);
        $this->assertCount(1, $buttons);
        $this->assertSame('登録', $xpath->evaluate('normalize-space(.)', $buttons->item(0)));
    }

    /** 正常な入力をして「登録」ボタンの押下で書籍が正常に登録される */
    public function testCreateBook(): void
    {
        // 1. Seederからテストデータ作成
        // 2. 作成されたユーザーで認証
        // 3. タイトル・著者・ISBN・出版日とgenres（小説のID配列）を指定し、
        //    任意の画像URL・説明は空文字でroute('books.store')にPOSTする
        // 4. テスト: 書籍と登録者がDBに保存され、選択したジャンルが紐付いている
        // 5. テスト: 書籍詳細画面にリダイレクトされ、登録内容が反映されている
        // 6. テスト: 「書籍を登録しました。」のフラッシュメッセージが表示される

        // 1
        $this->createTestDataFromSeeder();

        // 検証用データ作成
        $genre = Genre::where('name', '小説')->firstOrFail();
        $bookCount = Book::count();
        $postData = [
            'title' => 'テスト',
            'author' => 'テスト著者',
            'isbn' => '9780000000000',
            'published_date' => '2026-01-01',
            'image_url' => '',
            'description' => '',
            'genres' => [$genre->id],
        ];

        // 2,3
        $user = User::firstOrFail();
        $response = $this->actingAs($user)
            ->post(route('books.store'), $postData)
            ->assertSessionHasNoErrors();

        // 4
        $this->assertDatabaseCount('books', $bookCount + 1);
        $bookData = [
            'user_id' => $user->id,
            'title' => $postData['title'],
            'author' => $postData['author'],
            'isbn' => $postData['isbn'],
            'published_date' => $postData['published_date'],
            'image_url' => null,
            'description' => null,
        ];
        $this->assertDatabaseHas('books', $bookData);
        $book = Book::where('isbn', $postData['isbn'])->firstOrFail();
        $this->assertDatabaseHas('book_genre', [
            'book_id' => $book->id,
            'genre_id' => $genre->id,
        ]);

        // 5,6
        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍を登録しました。');
        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertViewHas('book', fn(Book $shownBook) => $shownBook->is($book))
            ->assertSeeText($postData['title'])
            ->assertSeeText($postData['author'])
            ->assertSeeText($postData['isbn'])
            ->assertSeeText($postData['published_date'])
            ->assertSeeText($genre->name)
            ->assertSeeText('書籍を登録しました。');
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(title編) */
    public function testTitleValidation(): void
    {
        // 1. テストデータを作成する
        // 2. 認証ユーザーとしてtitleを空にしてpostアクセス
        // 3. テスト: 登録画面に戻り、「書籍のタイトルを入力してください」が表示され、DB登録されていない
        // 4. titleを255文字超でpostアクセス
        // 5. テスト: 登録画面に戻り、「書籍のタイトルを255文字以内で入力してください」が表示され、DB登録されていない

        // 1
        $this->createTestDataFromSeeder();
        $bookCount = Book::count();
        $createUrl = route('books.create');

        // 2,3
        $this->from($createUrl)->postCreateBook(title: '')
            ->assertRedirect($createUrl)
            ->assertSessionHasErrors(['title' => '書籍のタイトルを入力してください'])
            ->assertSessionDoesntHaveErrors(['author', 'isbn', 'published_date', 'genres']);

        $this->get($createUrl)
            ->assertOk()
            ->assertSeeText('書籍のタイトルを入力してください');
        $this->assertDatabaseCount('books', $bookCount);

        // 4,5
        $this->from($createUrl)->postCreateBook(title: str_repeat('あ', 256))
            ->assertRedirect($createUrl)
            ->assertSessionHasErrors(['title' => '書籍のタイトルを255文字以内で入力してください'])
            ->assertSessionDoesntHaveErrors(['author', 'isbn', 'published_date', 'genres']);

        $this->get($createUrl)
            ->assertOk()
            ->assertSeeText('書籍のタイトルを255文字以内で入力してください');
        $this->assertDatabaseCount('books', $bookCount);
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(author編) */
    public function testAuthorValidation(): void
    {
        // 1. テストデータを作成する
        // 2. 認証ユーザーとしてauthorを空にしてpostアクセス
        // 3. テスト: 登録画面に戻り、「著者名を入力してください」が表示され、DB登録されていない
        // 4. authorを255文字超でpostアクセス
        // 5. テスト: 登録画面に戻り、「著者名を255文字以内で入力してください」が表示され、DB登録されていない

        // 1
        $this->createTestDataFromSeeder();
        $bookCount = Book::count();
        $createUrl = route('books.create');

        // 2,3
        $this->from($createUrl)->postCreateBook(author: '')
            ->assertRedirect($createUrl)
            ->assertSessionHasErrors(['author' => '著者名を入力してください'])
            ->assertSessionDoesntHaveErrors(['title', 'isbn', 'published_date', 'genres']);

        $this->get($createUrl)
            ->assertOk()
            ->assertSeeText('著者名を入力してください');
        $this->assertDatabaseCount('books', $bookCount);

        // 4,5
        $this->from($createUrl)->postCreateBook(author: str_repeat('あ', 256))
            ->assertRedirect($createUrl)
            ->assertSessionHasErrors(['author' => '著者名を255文字以内で入力してください'])
            ->assertSessionDoesntHaveErrors(['title', 'isbn', 'published_date', 'genres']);

        $this->get($createUrl)
            ->assertOk()
            ->assertSeeText('著者名を255文字以内で入力してください');
        $this->assertDatabaseCount('books', $bookCount);
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(isbn編) */
    public function testIsbnValidation(): void
    {
        // 1. テストデータを作成する
        // 2. 認証ユーザーとしてisbnを空にしてpostアクセス
        // 3. テスト: 登録画面に戻り、「ISBNを入力してください」が表示され、DB登録されていない
        // 4. ISBNを'978111111111-'にしてpostアクセス
        // 5. テスト: 登録画面に戻り、「ISBNを13桁の数字で入力してください（ハイフン不要）」が表示され、DB登録されていない
        // 6. ISBNをBook::findOrFail(1)->isbnにしてpostアクセス
        // 7. テスト: 登録画面に戻り、「このISBNは登録済みです」が表示され、DB登録されていない

        // 1
        $this->createTestDataFromSeeder();
        $bookCount = Book::count();
        $createUrl = route('books.create');

        // 検証用データ
        $cases = [
            ['', 'ISBNを入力してください'],
            ['978111111111-', 'ISBNを13桁の数字で入力してください（ハイフン不要）'],
            [Book::findOrFail(1)->isbn, 'このISBNは登録済みです'],
        ];

        // 2~7
        foreach ($cases as [$isbn, $message]) {
            $this->from($createUrl)->postCreateBook(isbn: $isbn)
                ->assertRedirect($createUrl)
                ->assertSessionHasErrors(['isbn' => $message])
                ->assertSessionDoesntHaveErrors(['title', 'author', 'published_date', 'genres']);

            $this->get($createUrl)
                ->assertOk()
                ->assertSeeText($message);
            $this->assertDatabaseCount('books', $bookCount);
        }
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(published_date編) */
    public function testPublishedDateValidation(): void
    {
        // 1. テストデータを作成する
        // 2. 認証ユーザーとしてpublished_dateを空にしてpostアクセス
        // 3. テスト: 登録画面に戻り、「出版日を入力してください」が表示され、DB登録されていない

        // 1 
        $this->createTestDataFromSeeder();
        $bookCount = Book::count();
        $createUrl = route('books.create');

        // 2,3
        $this->from($createUrl)->postCreateBook(publishDate: '')
            ->assertRedirect($createUrl)
            ->assertSessionHasErrors(['published_date' => '出版日を入力してください'])
            ->assertSessionDoesntHaveErrors(['title', 'author', 'isbn', 'genres']);

        $this->get($createUrl)
            ->assertOk()
            ->assertSeeText('出版日を入力してください');
        $this->assertDatabaseCount('books', $bookCount);
    }

    /** 不正項目の入力に対して、バリデーションメッセージが表示される(genres編) */
    public function testGenresValidation(): void
    {
        // 1. テストデータを作成する
        // 2. 認証ユーザーとしてgenresを空にしてpostアクセス
        // 3. テスト: 登録画面に戻り、「ジャンルを選択してください」が表示され、DB登録されていない

        $this->createTestDataFromSeeder();
        $bookCount = Book::count();
        $createUrl = route('books.create');

        $this->from($createUrl)->postCreateBook(genreNames: [])
            ->assertRedirect($createUrl)
            ->assertSessionHasErrors(['genres' => 'ジャンルを選択してください'])
            ->assertSessionDoesntHaveErrors(['title', 'author', 'isbn', 'published_date']);

        $this->get($createUrl)
            ->assertOk()
            ->assertSeeText('ジャンルを選択してください');
        $this->assertDatabaseCount('books', $bookCount);
    }


    /** 不正項目の入力に対して、バリデーションメッセージが表示される(image_url編) */
    public function testImageUrlValidation(): void
    {
        // 1. テストデータを作成する
        // 2. 認証ユーザーとしてimage_urlを'https:/test'にしてpostアクセス
        // 3. テスト: 登録画面に戻り、「URL形式で入力してください」が表示され、DB登録されていない
        // 4. 認証ユーザーとしてimage_urlをURL形式で2049文字にしてpostアクセス
        // 5. テスト: 登録画面に戻り、「URLは2048文字以内で入力してください」が表示され、DB登録されていない

        $this->createTestDataFromSeeder();
        $bookCount = Book::count();
        $createUrl = route('books.create');
        $urlPrefix = 'https://example.com/';
        $cases = [
            ['https:/test', 'URL形式で入力してください'],
            [$urlPrefix . str_repeat('a', 2049 - strlen($urlPrefix)), 'URLは2048文字以内で入力してください'],
        ];

        foreach ($cases as [$imageUrl, $message]) {
            $this->from($createUrl)->postCreateBook(imageUrl: $imageUrl)
                ->assertRedirect($createUrl)
                ->assertSessionHasErrors(['image_url' => $message])
                ->assertSessionDoesntHaveErrors(['title', 'author', 'isbn', 'published_date', 'genres']);

            $this->get($createUrl)
                ->assertOk()
                ->assertSeeText($message);
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
