<?php

namespace Tests\Feature\Genre;

use App\Models\User;
use App\Models\Genre;
use Database\Seeders\UserSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\ReviewSeeder;
use Database\Seeders\FavoriteSeeder;
use Database\Seeders\ReviewLikeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexGenreTest extends TestCase
{
    use RefreshDatabase;

    /** ゲストが書籍一覧のジャンル管理リンクを押下するとログイン画面にリダイレクトする */
    public function testGuestCannotViewGenres(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. ゲストとして書籍一覧画面にアクセス
        // 3. ジャンル管理ボタンのリンク先にアクセス
        // 4. テスト: ログイン画面にリダイレクトする

        // 1
        $this->createTestDataFromSeeder();
        $genresUrl = route('genres.index');

        // 2
        $this->get('/')
            ->assertOk()
            ->assertViewIs('books.index')
            ->assertSee('href="' . $genresUrl . '"', false);

        // 3
        $this->get($genresUrl)->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /** 認証ユーザーはページが正常に表示される */
    public function testGenresView(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. 認証ユーザーとして書籍一覧画面にアクセス
        // 3. ジャンル管理ボタンのリンク先にアクセス
        // 4. テスト: ジャンル一覧画面に遷移する

        // 1
        $this->createTestDataFromSeeder();
        $user = User::firstOrFail();
        $genresUrl = route('genres.index');

        // 2
        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertViewIs('books.index')
            ->assertSee('href="' . $genresUrl . '"', false);

        // 3,4
        $this->get($genresUrl)
            ->assertOk()
            ->assertViewIs('genres.index')
            ->assertSeeText('ジャンル管理');
    }

    /**
     * ジャンル名を押すと対象ジャンルの詳細画面に遷移する
     */
    public function testGenreShowLink(): void
    {
        // 1. seederからテストデータ作成
        // 2. 認証ユーザーとしてジャンル一覧画面にアクセス
        // 3. ジャンル名のリンク先にアクセス
        // 4. テスト: ジャンル詳細画面に遷移する

        // 1
        $this->createTestDataFromSeeder();
        $user = User::firstOrFail();
        $genre = Genre::firstOrFail();
        $showUrl = route('genres.show', $genre);

        // 2
        $response = $this->actingAs($user)
            ->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index');

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//a[@href="' . $showUrl . '"]');
        $this->assertCount(1, $links);
        $this->assertSame($genre->name, $xpath->evaluate('normalize-space(.)', $links->item(0)));

        // 4
        $this->get($links->item(0)->getAttribute('href'))
            ->assertOk()
            ->assertViewIs('genres.show')
            ->assertViewHas('genre', fn (Genre $shownGenre) => $shownGenre->is($genre))
            ->assertSeeText($genre->name);
    }

    /** 「ジャンルを登録」ボタンを押下し、ジャンル登録画面に遷移する */
    public function testGenreCreateLink(): void
    {
        // 1. seederからテストデータ作成
        // 2. 認証ユーザーとしてジャンル一覧画面にアクセス
        // 3. ジャンル登録ボタンのリンク先にアクセス
        // 4. テスト: ジャンル登録画面に遷移する

        // 1
        $this->createTestDataFromSeeder();
        $createUrl = route('genres.create');

        // 2
        $response = $this->actingAs(User::firstOrFail())
            ->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index');

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//a[@href="' . $createUrl . '"]');
        $this->assertCount(1, $links);
        $this->assertSame('ジャンルを登録', $xpath->evaluate('normalize-space(.)', $links->item(0)));

        // 4
        $this->get($links->item(0)->getAttribute('href'))
            ->assertOk()
            ->assertViewIs('genres.create');
    }

    /** ジャンルの「編集」ボタンを押下するとジャンル編集画面に遷移する */
    public function testGenreEditLink(): void
    {
        // 1. seederからテストデータ作成
        // 2. 認証ユーザーとしてジャンル一覧画面にアクセス
        // 3. 編集ボタンのリンク先にアクセス
        // 4. テスト: ジャンル編集画面に遷移する

        // 1
        $this->createTestDataFromSeeder();
        $genre = Genre::firstOrFail();
        $editUrl = route('genres.edit', $genre);

        // 2
        $response = $this->actingAs(User::firstOrFail())
            ->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index');

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//a[@href="' . $editUrl . '"]');
        $this->assertCount(1, $links);
        $this->assertSame('編集', $xpath->evaluate('normalize-space(.)', $links->item(0)));

        // 4
        $this->get($links->item(0)->getAttribute('href'))
            ->assertOk()
            ->assertViewIs('genres.edit')
            ->assertViewHas('genre', fn (Genre $shownGenre) => $shownGenre->is($genre));
    }

    /** 書籍に紐づいていないジャンルの「削除」ボタンを押して、ジャンルを削除できる */
    public function testDeleteUnusedGenre(): void
    {
        // 1. ジャンルを生成
        // 2. 認証ユーザーとしてジャンル一覧画面にアクセス
        // 3. 対象ジャンルの削除フォームとボタンを確認し、DELETE送信
        // 4. テスト: ジャンルが削除され、一覧画面に削除が反映される

        // 1
        $genre = Genre::create(['name' => '未使用のテストジャンル']);
        $user = User::factory()->create();
        $deleteUrl = route('genres.destroy', $genre);
        $this->assertFalse($genre->books()->exists());

        // 2
        $response = $this->actingAs($user)
            ->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index');

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@action="' . $deleteUrl . '"]');
        $this->assertCount(1, $forms);
        $form = $forms->item(0);
        $this->assertSame('POST', strtoupper($form->getAttribute('method')));
        $this->assertCount(1, $xpath->query('.//input[@name="_method" and @value="DELETE"]', $form));
        $this->assertSame('削除', $xpath->evaluate('normalize-space(.//button)', $form));

        // 4
        $this->delete($deleteUrl)
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを削除しました。');

        // DBからも削除されたことを確認
        $this->assertDatabaseMissing('genres', ['id' => $genre->id]);
        $this->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index')
            ->assertSeeText('ジャンルを削除しました。')
            ->assertDontSeeText($genre->name);
    }

    /** 書籍に紐づいているジャンルの「削除」ボタンを押した場合、ジャンルを削除できない */
    public function testCannotDeleteUsedGenre(): void
    {
        // 1. seederからテストデータを生成
        // 2. 認証ユーザーとしてジャンル一覧画面にアクセス
        // 3. 書籍に紐づくジャンルの削除フォームを確認し、DELETE送信
        // 4. テスト: ジャンル一覧画面にリダイレクトし、「このジャンルには書籍が紐付いているため削除できません。」というフラッシュメッセージが表示される
        // 5. テスト: DBにジャンルと書籍の紐付けが残っていることを確認

        // 1
        $this->createTestDataFromSeeder();
        $genre = Genre::whereHas('books')->firstOrFail();

        // 検証データ
        $original = $genre->getAttributes();
        $bookIds = $genre->books()->orderBy('books.id')->pluck('books.id')->all();
        $genreCount = Genre::count();
        $deleteUrl = route('genres.destroy', $genre);
        $message = 'このジャンルには書籍が紐付いているため削除できません。';

        // 2
        $response = $this->actingAs(User::firstOrFail())
            ->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index');

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@action="' . $deleteUrl . '"]');
        $this->assertCount(1, $forms);
        $form = $forms->item(0);
        $this->assertSame('POST', strtoupper($form->getAttribute('method')));
        $this->assertCount(1, $xpath->query('.//input[@name="_method" and @value="DELETE"]', $form));
        $this->assertSame('削除', $xpath->evaluate('normalize-space(.//button)', $form));

        // 4
        $this->delete($deleteUrl)
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('error', $message)
            ->assertSessionMissing('success');

        $this->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index')
            ->assertSeeText($message)
            ->assertSeeText($genre->name);

        // 5
        $this->assertSame($original, $genre->fresh()->getAttributes());
        $this->assertDatabaseCount('genres', $genreCount);
        $this->assertSame($bookIds, $genre->books()->orderBy('books.id')->pluck('books.id')->all());
        foreach ($bookIds as $bookId) {
            $this->assertDatabaseHas('book_genre', ['genre_id' => $genre->id, 'book_id' => $bookId]);
        }
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
