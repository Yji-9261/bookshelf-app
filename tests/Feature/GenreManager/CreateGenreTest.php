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

class CreateGenreTest extends TestCase
{
    use RefreshDatabase;

    public const NAME = 'テスト';

    private function postCreateGenre($name = self::NAME)
    {
        return $this->actingAs(User::findOrFail(1))
            ->post(route('genres.store'), [
                'name' => $name,
            ]);
    }


    /** ゲストがジャンル登録リンクを押下するとログイン画面にリダイレクトする */
    public function testGuestCannotCreateGenre(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. ゲストとしてジャンル登録画面にアクセス
        // 3. テスト: ログイン画面にリダイレクトする

        // 1
        $this->createTestDataFromSeeder();

        // 2,3
        $this->get(route('genres.create'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    /** 認証ユーザーはページが正常に表示される */
    public function testCreateView(): void
    {
        // 1. Seederからテストデータを作成する
        // 2. 認証ユーザーとしてジャンル登録画面にアクセス
        // 3. テスト: ジャンル名入力フォームが表示される

        // 1
        $this->createTestDataFromSeeder();

        // 2
        $response = $this->actingAs(User::firstOrFail())
            ->get(route('genres.create'))
            ->assertOk()
            ->assertViewIs('genres.create')
            ->assertSeeText('ジャンル登録');

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@action="' . route('genres.store') . '"]');
        $this->assertCount(1, $forms);
        $form = $forms->item(0);
        $this->assertSame('POST', strtoupper($form->getAttribute('method')));
        $inputs = $xpath->query('.//input[@type="text" and @name="name"]', $form);
        $this->assertCount(1, $inputs);
        $this->assertSame('', $inputs->item(0)->getAttribute('value'));
        $this->assertCount(1, $xpath->query('.//button[@type="submit"]', $form));
    }

    /**
     * 正常な入力をして「登録」ボタンを押下する
     */
    public function testCreateGenre(): void
    {
        // 1. seederからテストデータ作成
        // 2. 認証ユーザーとしてnameを指定しPost送信
        // 3. テスト: ジャンル一覧にリダイレクトされ、内容が反映されている
        // 4. テスト: DBに登録されている

        // 1
        $this->createTestDataFromSeeder();
        $genreCount = Genre::count();

        // 2,3
        $this->postCreateGenre()
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを作成しました。');

        $this->get(route('genres.index'))
            ->assertOk()
            ->assertViewIs('genres.index')
            ->assertSeeText(self::NAME)
            ->assertSeeText('ジャンルを作成しました。');

        // 4
        $this->assertDatabaseHas('genres', ['name' => self::NAME]);
        $this->assertDatabaseCount('genres', $genreCount + 1);
    }

    /** 不正な入力をして「登録」ボタンを押下する */
    public function testNameValidation(): void
    {
        // 1. seederからテストデータ作成
        // 2. postCreateGenre(name:'')を実行
        // 3. テスト: ジャンル登録画面にリダイレクトされ「ジャンルを入力してください」メッセージが表示される
        // 4. テスト: DBに登録されない
        // 5. postCreateGenre(name:'小説')を実行
        // 6. テスト: ジャンル登録画面にリダイレクトされ「このジャンルはすでに登録されています」メッセージが表示される
        // 7. テスト: DBに登録されない

        // 1
        $this->createTestDataFromSeeder();

        // 検証データ
        $genreCount = Genre::count();
        $createUrl = route('genres.create');
        $cases = [
            ['', 'ジャンルを入力してください'],
            ['小説', 'このジャンルはすでに登録されています'],
        ];

        // 2~7
        foreach ($cases as [$name, $message]) {
            $this->from($createUrl)->postCreateGenre(name: $name)
                ->assertRedirect($createUrl)
                ->assertSessionHasErrors(['name' => $message]);

            $this->get($createUrl)
                ->assertOk()
                ->assertViewIs('genres.create')
                ->assertSeeText($message);

            $this->assertDatabaseCount('genres', $genreCount);
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
