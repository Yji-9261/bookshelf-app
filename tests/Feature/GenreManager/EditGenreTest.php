<?php

namespace Tests\Feature\Genre;

use App\Models\Genre;
use App\Models\User;
use Database\Seeders\BookSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EditGenreTest extends TestCase
{
    use RefreshDatabase;

    public const NAME = 'テスト';

    /**
     * 引数に与えられた内容でジャンル更新urlにPUT送信
     *
     * @param  Genre  $genre  更新対象のジャンルモデル
     * @param  mixed  $name  変更先のジャンル名
     */
    private function putUpdateGenre(
        Genre $genre,
        mixed $name = self::NAME
    ): TestResponse {
        return $this->actingAs(User::findOrFail(1))
            ->put(route('genres.update', $genre), [
                'name' => $name,
            ]);
    }

    /**
     * ゲストがジャンル編集リンクを押下するとログイン画面にリダイレクトする
     */
    public function testGuestCannotEditGenre(): void {
        // 1. Seederからテストデータを作成する
        // 2. ゲストとしてジャンル編集画面にアクセス
        // 3. テスト: ログイン画面にリダイレクトする

        // 1
        $this->createTestDataFromSeeder();
        $genre = Genre::firstOrFail();

        // 2
        $this->get(route('genres.edit', $genre))
            ->assertRedirect(route('login'));

        // 3
        $this->assertGuest();
    }

    /**
     * 認証ユーザーはページが正常に表示される
     */
    public function testGenreEditView(): void {
        // 1. Seederからテストデータを作成する
        // 2. 認証ユーザーとしてジャンル編集画面にアクセス
        // 3. テスト: 選択されたジャンル名が初期値として表示された編集フォームが表示される

        // 1
        $this->createTestDataFromSeeder();
        $genre = Genre::firstOrFail();

        // 2
        $response = $this->actingAs(User::firstOrFail())
            ->get(route('genres.edit', $genre))
            ->assertOk()
            ->assertViewIs('genres.edit')
            ->assertViewHas('genre', fn (Genre $shownGenre) => $shownGenre->is($genre));

        // 3
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@action="' . route('genres.update', $genre) . '"]');
        $this->assertCount(1, $forms);
        $form = $forms->item(0);
        $this->assertSame('POST', strtoupper($form->getAttribute('method')));
        $this->assertCount(1, $xpath->query('.//input[@name="_method" and @value="PUT"]', $form));
        $inputs = $xpath->query('.//input[@type="text" and @name="name"]', $form);
        $this->assertCount(1, $inputs);
        $this->assertSame($genre->name, $inputs->item(0)->getAttribute('value'));
    }

    /**
     * 正常な入力をして「更新」ボタンを押下すると、更新に成功する
     */
    public function testUpdateGenre(): void {
        // 1. seederからテストデータ作成
        // 2. 認証ユーザーとして対象ジャンルを指定し、putUpdateGenre($genre)実行
        // 3. テスト: ジャンル一覧にリダイレクトされ、内容が反映されている
        // 4. テスト: 対象ジャンル名が更新され、ジャンル件数と書籍との紐付けは変わらない

        $this->createTestDataFromSeeder();
        $genre = Genre::whereHas('books')->firstOrFail();
        $genreCount = Genre::count();
        $bookIds = $genre->books()->orderBy('books.id')->pluck('books.id')->all();

        $this->putUpdateGenre(genre: $genre)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを更新しました。');

        $this->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index')
            ->assertSeeText(self::NAME)
            ->assertSeeText('ジャンルを更新しました。');

        $this->assertDatabaseHas('genres', ['id' => $genre->id, 'name' => self::NAME]);
        $this->assertDatabaseCount('genres', $genreCount);
        $this->assertSame($bookIds, $genre->books()->orderBy('books.id')->pluck('books.id')->all());
    }

    /**
     * nameフィールドを不正なリクエストでPUT送信したとき、更新に失敗する
     */
    public function testNameValidation(): void {
        // 1. seederからテストデータ作成
        // 2. $genre = Genre::where('name','旅行')->firstOrFail();
        // 3. putUpdateGenre(genre: $genre, name:'')を実行
        // 4. テスト: ジャンル編集画面にリダイレクトされ「ジャンルを入力してください」メッセージが表示される
        // 5. テスト: DBの内容が更新されない
        // 6. putUpdateGenre(genre: $genre, name:'小説')を実行
        // 7. テスト: ジャンル編集画面にリダイレクトされ「このジャンルはすでに登録されています」メッセージが表示される
        // 8. テスト: DBの内容が更新されない

        // 1
        $this->createTestDataFromSeeder();

        // 2
        $genre = Genre::where('name', '旅行')->firstOrFail();

        // 検証データ
        $original = $genre->getAttributes();
        $genreCount = Genre::count();
        $editUrl = route('genres.edit', $genre);
        $cases = [
            ['', 'ジャンルを入力してください'],
            ['小説', 'このジャンルはすでに登録されています'],
            [str_repeat('あ', 256), 'ジャンルを255文字以内で入力してください'],
        ];

        // 3~8
        foreach ($cases as [$name, $message]) {
            $this->from($editUrl)->putUpdateGenre(genre: $genre, name: $name)
                ->assertRedirect($editUrl)
                ->assertSessionHasErrors(['name' => $message]);

            $this->get($editUrl)
                ->assertOk()
                ->assertViewIs('genres.edit')
                ->assertSeeText($message);

            $this->assertSame($original, $genre->fresh()->getAttributes());
            $this->assertDatabaseCount('genres', $genreCount);
        }
    }

    /**
     * seederからテストデータ生成
     */
    private function createTestDataFromSeeder(): void {
        $this->seed([
            UserSeeder::class,
            GenreSeeder::class,
            BookSeeder::class,
        ]);
    }
}
