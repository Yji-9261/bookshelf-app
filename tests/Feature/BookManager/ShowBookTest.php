<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Book;
use Database\Seeders\UserSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\ReviewSeeder;
use Database\Seeders\FavoriteSeeder;
use Database\Seeders\ReviewLikeSeeder;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;

class ShowBookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 書籍タイトル・著者・ISBN・出版日・説明・画像・ジャンル・レビュー一覧・いいね数が表示される
     */
    public function testViewDetailInfo(): void
    {
        // 1. Seederから書籍・ジャンル・レビュー・いいねを作成
        $this->createTestDataFromSeeder();
        $book = Book::with(['genres', 'reviews.user', 'reviews.likedByUsers'])->findOrFail(1);

        // 2. 対象書籍の詳細にアクセス
        $response = $this->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertViewHas('book', fn(Book $shownBook) => $shownBook->is($book));

        // 3. Seederで作成した書籍情報・画像が表示される
        foreach (['title', 'author', 'isbn', 'published_date', 'description'] as $field) {
            $response->assertSeeText($book->$field);
        }
        $response->assertSee('src="' . $book->image_url . '"', false);

        // 4. 対象書籍のジャンルが表示される
        $this->assertNotEmpty($book->genres);
        foreach ($book->genres as $genre) {
            $response->assertSeeText($genre->name);
        }

        // 5. レビュー一覧と各レビューのいいね数が表示される
        $this->assertNotEmpty($book->reviews);
        foreach ($book->reviews as $review) {
            $response->assertSeeText($review->user->name)
                ->assertSeeText($review->comment)
                ->assertSeeText(str_repeat('★', $review->rating) . str_repeat('☆', 5 - $review->rating))
                ->assertSeeText($review->created_at->format('Y/m/d'))
                ->assertSeeText('いいね (' . $review->likedByUsers->count() . ')');
        }
    }

    /**
     * ゲストはお気に入りトグルを押下するとログイン画面にリダイレクトされる
     */
    public function testGuestCannotFavorite(): void
    {
        // 1. ゲストとして書籍詳細画面にアクセスする
        // 2. お気に入りトグルを押下した時と同じアクションをする
        // 3. テスト: ログイン画面にリダイレクトする

        $this->createTestDataFromSeeder();

        $book = Book::findOrFail(1);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show');

        $this->post(route('favorites.toggle', $book))
            ->assertRedirect(route('login'));
    }

    /** 
     * 認証ユーザーはお気に入りトグルを押すごとにお気に入りの登録→解除→登録→...が可能
     */
    public function testFavoriteToggle(): void
    {
        // 1.認証ユーザーがお気に入り登録していないテスト用の書籍データを用意する
        // 2.認証ユーザーとして作成した書籍の詳細画面にアクセスする
        // 3.お気に入りトグルを押下する
        // 4.テスト: 書籍詳細画面にリダイレクトされ
        //          対象書籍がお気に入りの状態でDBに格納されている
        //          トグルがお気に入りの状態になっている
        //          フラッシュメッセージが適切である
        // 5.お気に入りトグルを押下する
        // 6.テスト: 書籍詳細画面にリダイレクトされ
        //          favoritesテーブルに対象のuser_idとbook_idの行が存在しない
        //          トグルがお気に入りではない状態になっている
        //          フラッシュメッセージが適切である
        // 7.お気に入りトグルを押下する
        // 8.テスト: 書籍詳細画面にリダイレクトされ
        //          対象書籍がお気に入りの状態でDBに格納されている
        //          トグルがお気に入りの状態になっている
        //          フラッシュメッセージが適切である

        // 1
        $this->createTestDataFromSeeder();
        $user = User::findOrFail(1);
        $book = Book::whereDoesntHave('favoriteUsers', fn($query) => $query->whereKey($user->id))
            ->firstOrFail();

        // 検証用のデータ
        $favorite = ['user_id' => $user->id, 'book_id' => $book->id];
        $showUrl = route('books.show', $book);
        $toggleUrl = route('favorites.toggle', $book);

        // 2
        $this->actingAs($user);
        $this->assertDatabaseMissing('favorites', $favorite);
        $this->get($showUrl)
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertSee('action="' . $toggleUrl . '"', false)
            ->assertSee('title="お気に入りに追加"', false);

        // 3~8
        foreach ([true, false, true] as $registered) {
            $message = $registered ? 'お気に入り登録しました。' : 'お気に入り解除しました。';
            $this->post($toggleUrl)
                ->assertRedirect($showUrl)
                ->assertSessionHas('success', $message);

            if ($registered) {
                $this->assertDatabaseHas('favorites', $favorite);
            } else {
                $this->assertDatabaseMissing('favorites', $favorite);
            }

            // 同じテスト内で読み込んだリレーションを更新
            $user->refresh();
            $buttonTitle = $registered ? 'お気に入りから削除' : 'お気に入りに追加';
            $otherTitle = $registered ? 'お気に入りに追加' : 'お気に入りから削除';
            $this->get($showUrl)
                ->assertOk()
                ->assertViewIs('books.show')
                ->assertSeeText($message)
                ->assertSee('title="' . $buttonTitle . '"', false)
                ->assertDontSee('title="' . $otherTitle . '"', false);
        }
    }

    /**
     * ゲストはレビュー投稿フォームが存在せず、ログイン画面に誘導するリンクがある
     */
    public function testGuestCannotPostReview(): void
    {
        // 1.ゲストとして書籍詳細画面にアクセス
        // 2.テスト: レビュー投稿フォームが存在せず、ログイン画面リンクが存在する
        // 3.ログイン画面リンクアクセス
        // 4.テスト: ログイン画面に遷移する

        // 初期データ作成
        $this->createTestDataFromSeeder();
        $book = Book::findOrFail(1);
        $loginUrl = route('login');

        // 1,2
        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertDontSee('action="' . route('reviews.store', $book) . '"', false)
            ->assertDontSee('name="rating"', false)
            ->assertDontSee('name="comment"', false)
            ->assertSeeText('レビューを投稿するには')
            ->assertSee('href="' . $loginUrl . '"', false);

        // 3,4
        $this->get($loginUrl)
            ->assertOk()
            ->assertViewIs('auth.login');
    }

    /**
     * 認証ユーザーはレビュー投稿フォームに入力し、投稿できる
     */
    public function testAuthUserCanPostReview(): void
    {
        // 1.認証ユーザーとして書籍詳細画面にアクセス
        // 2.レビューフォームにratingとcommentを記入し投稿
        // 3:テスト: DBにレビュー内容が保存されている
        // 4:テスト: 書籍詳細画面にリダイレクトされ、フラッシュメッセージが適切に表示されている
        // 5:テスト: 書籍詳細画面にリダイレクトされ、レビューが反映されている

        // 初期データ生成
        $this->createTestDataFromSeeder();
        $user = User::factory()->create();
        $book = Book::findOrFail(1);
        $showUrl = route('books.show', $book);
        $storeUrl = route('reviews.store', $book);
        $reviewData = [
            'rating' => 4,
            'comment' => '投稿テスト用の感想です。読みやすい本でした。',
        ];

        // 1
        $this->actingAs($user);
        $this->get($showUrl)
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertSee('action="' . $storeUrl . '"', false)
            ->assertSee('name="rating"', false)
            ->assertSee('name="comment"', false);

        // 2
        $response = $this->post($storeUrl, $reviewData);
        $response->assertSessionHasNoErrors();

        // 3
        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'book_id' => $book->id,
            ...$reviewData,
        ]);

        // 4
        $response->assertRedirect($showUrl)
            ->assertSessionHas('success', 'レビューを投稿しました。');

        // 5
        $this->get($showUrl)
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertSeeText($user->name)
            ->assertSeeText($reviewData['comment'])
            ->assertSeeText('★★★★☆')
            ->assertSeeText('レビューを投稿しました。');
    }

    /** ゲストはレビューの「いいね」リンクからログイン画面に遷移する */
    public function testGuestCannotLike(): void
    {
        // 1. ゲストとしてレビューの存在する書籍詳細画面にアクセス
        // 2. レビューの「いいね」がログイン画面へのリンクであることを確認
        // 3. いいねリンクの遷移先でログイン画面が表示される

        // 1
        $this->createTestDataFromSeeder();
        $book = Book::whereHas('reviews')->firstOrFail();
        $response = $this->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show');

        // 2
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $likeLinks = $xpath->query('//a[contains(normalize-space(.), "いいね")]');
        $this->assertCount($book->reviews()->count(), $likeLinks);

        foreach ($likeLinks as $link) {
            $this->assertSame(route('login'), $link->getAttribute('href'));
        }

        // 3
        $this->get($likeLinks->item(0)->getAttribute('href'))
            ->assertOk()
            ->assertViewIs('auth.login');
    }

    /** 認証ユーザーはレビューのいいねを登録・解除・再登録できる */
    public function testLikeToggle(): void
    {
        // 1. Seederからレビューを作成し、いいね未登録の認証ユーザーを用意する
        $this->createTestDataFromSeeder();
        $user = User::factory()->create();
        $book = Book::whereHas('reviews')->firstOrFail();
        $review = $book->reviews()->firstOrFail();

        // 検証用データ生成
        $like = ['user_id' => $user->id, 'review_id' => $review->id];
        $showUrl = route('books.show', $book);
        $toggleUrl = route('reviews.like', $review);
        $initialLikeCount = $review->likedByUsers()->count();

        // 対象レビューのフォームに限定して、ボタンの状態といいね数を確認する
        $assertLikeButton = function ($response, string $expected) use ($toggleUrl): void {
            $document = new \DOMDocument();
            @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
            $xpath = new \DOMXPath($document);
            $buttons = $xpath->query('//form[@action="' . $toggleUrl . '"]//button');
            $this->assertCount(1, $buttons);
            $this->assertSame($expected, $xpath->evaluate('normalize-space(.)', $buttons->item(0)));
        };

        // 2. 詳細画面で未登録のボタン表示を確認する
        $this->actingAs($user);
        $this->assertDatabaseMissing('review_likes', $like);
        $response = $this->get($showUrl)->assertOk()->assertViewIs('books.show');
        $assertLikeButton($response, 'いいね (' . $initialLikeCount . ')');

        // 3〜5. 登録・解除・再登録し、DB・遷移先・メッセージ・ボタン表示・件数を確認する
        foreach ([true, false, true] as $registered) {
            $message = $registered ? 'いいねを登録しました。' : 'いいねを解除しました。';
            $this->post($toggleUrl)
                ->assertRedirect($showUrl)
                ->assertSessionHas('success', $message);

            if ($registered) {
                $this->assertDatabaseHas('review_likes', $like);
            } else {
                $this->assertDatabaseMissing('review_likes', $like);
            }

            $user->refresh();
            $response = $this->get($showUrl)
                ->assertOk()
                ->assertViewIs('books.show')
                ->assertSeeText($message);
            $count = $initialLikeCount + ($registered ? 1 : 0);
            $label = $registered ? 'いいね済み' : 'いいね';
            $assertLikeButton($response, $label . ' (' . $count . ')');
        }
    }

    /** 自分のレビューの「編集」ボタンを押下する */
    public function testOwnReviewEditLink(): void
    {
        // 1. 認証ユーザーとして、自分のレビューが登録されている書籍詳細画面にアクセス
        $this->createTestDataFromSeeder();
        $user = User::findOrFail(1);
        $review = $user->postReviews()->firstOrFail();
        $editUrl = route('reviews.edit', $review);

        $this->actingAs($user);
        $this->get(route('books.show', $review->book))
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertSee('href="' . $editUrl . '"', false);

        // 2. 「編集」リンク先にアクセス
        // 3. テスト: 編集画面が表示され、対象レビューが渡されている
        $this->get($editUrl)
            ->assertOk()
            ->assertViewIs('reviews.edit');
    }

    /** 自分のレビューの「削除」ボタンを押下する */
    public function testOwnReviewDelete(): void
    {
        // 1. 認証ユーザーとして、自分のレビューが登録されている書籍詳細画面にアクセス
        $this->createTestDataFromSeeder();
        $user = User::findOrFail(1);
        $review = $user->postReviews()->firstOrFail();
        $showUrl = route('books.show', $review->book);
        $deleteUrl = route('reviews.destroy', $review);

        $this->actingAs($user);
        $response = $this->get($showUrl)
            ->assertOk()
            ->assertViewIs('books.show');

        // 対象レビューの削除フォームとDELETE指定を確認
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@action="' . $deleteUrl . '"]');
        $this->assertCount(1, $forms);
        $this->assertSame('POST', strtoupper($forms->item(0)->getAttribute('method')));
        $this->assertCount(1, $xpath->query('.//input[@name="_method" and @value="DELETE"]', $forms->item(0)));
        $this->assertSame('削除', $xpath->evaluate('normalize-space(.//button)', $forms->item(0)));

        // 2. 削除ボタンの送信先へDELETEリクエストを送る
        $this->delete($deleteUrl)
            ->assertRedirect($showUrl)
            ->assertSessionHas('success', 'レビューを削除しました。');

        // 3. レビューが削除され、詳細画面から対象の編集・削除操作が消える
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->get($showUrl)
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertSeeText('レビューを削除しました。')
            ->assertDontSee('action="' . $deleteUrl . '"', false)
            ->assertDontSee('href="' . route('reviews.edit', $review) . '"', false);
    }

    /** 自分以外のレビューに「編集」「削除」ボタンが表示されない */
    public function testOtherReviewActionsHidden(): void
    {
        // 1. 他のユーザーのレビューが存在する書籍を用意し、未投稿のユーザーとしてアクセス
        // 2. 未投稿ユーザーとして認証し、書籍詳細画面にアクセス
        // 3. テスト: レビューの編集と削除リンクが存在しない

        // 1
        $this->createTestDataFromSeeder();
        $user = User::factory()->create();
        $book = Book::with('reviews')->whereHas('reviews')->firstOrFail();

        // 2
        $response = $this->actingAs($user)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show');

        // 3:テスト
        $this->assertNotEmpty($book->reviews);
        foreach ($book->reviews as $review) {
            $this->assertNotSame($user->id, $review->user_id);
            $response->assertSeeText($review->comment)
                ->assertDontSee('href="' . route('reviews.edit', $review) . '"', false)
                ->assertDontSee('action="' . route('reviews.destroy', $review) . '"', false);
        }
    }

    /** 自分が登録した書籍の「編集」ボタンを押下する */
    public function testOwnBookEditLink(): void
    {
        // 1. 認証ユーザーが登録した書籍詳細画面にアクセス
        // 2. テスト: 書籍編集リンクが存在するか確認
        // 3. 書籍編集リンク先にアクセス
        // 4. テスト: 書籍編集画面が正しく表示されているか確認

        $this->createTestDataFromSeeder();
        $user = User::findOrFail(1);
        $book = $user->registeredBooks()->firstOrFail();
        $editUrl = route('books.edit', $book);

        // 1,2
        $this->actingAs($user)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertSee('href="' . $editUrl . '"', false);

        // 3,4
        $this->get($editUrl)
            ->assertOk()
            ->assertViewIs('books.edit')
            ->assertViewHas('book', fn(Book $shownBook) => $shownBook->is($book));
    }
    /** 自分が登録した書籍の「削除」ボタンを押下する */
    public function testOwnBookDelete(): void
    {
        // 1. 認証ユーザーとして、自分の登録した書籍詳細画面にアクセス
        // 2. テスト: 対象書籍の削除フォームと削除ボタンが存在する
        // 3. 削除フォームの送信先にDELETEリクエストを送る
        // 4. テスト: 対象書籍が削除され、書籍一覧画面に遷移し、削除が反映されている

        $this->createTestDataFromSeeder();
        $user = User::findOrFail(1);
        $book = $user->registeredBooks()->firstOrFail();
        $deleteUrl = route('books.destroy', $book);
        $indexUrl = route('books.index');

        // 1
        $response = $this->actingAs($user)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show');

        // 2
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $forms = $xpath->query('//form[@action="' . $deleteUrl . '"]');
        $this->assertCount(1, $forms);
        $this->assertSame('POST', strtoupper($forms->item(0)->getAttribute('method')));
        $this->assertCount(1, $xpath->query('.//input[@name="_method" and @value="DELETE"]', $forms->item(0)));
        $this->assertSame('削除', $xpath->evaluate('normalize-space(.//button)', $forms->item(0)));

        // 3
        $deleteResponse = $this->delete($deleteUrl);

        // 4
        $deleteResponse->assertRedirect($indexUrl)
            ->assertSessionHas('success', '書籍を削除しました。');
        $this->assertDatabaseMissing('books', ['id' => $book->id]);
        $this->followRedirects($deleteResponse)
            ->assertOk()
            ->assertViewIs('books.index')
            ->assertDontSeeText($book->title)
            ->assertDontSee('href="' . route('books.show', $book) . '"', false);
    }

    /** 自分以外が登録した書籍に「編集」「削除」ボタンが表示されない */
    public function testOtherBookActionsHidden(): void
    {
        // 1. Seederから一連のテストデータを作成
        // 2. 書籍の登録者とは別のユーザーを作成
        // 3. 作成したユーザーで認証し、他のユーザーの書籍詳細にアクセス
        // 4. テスト: 対象書籍の編集リンク・削除フォームが表示されない

        // 1
        $this->createTestDataFromSeeder();
        // 2
        $user = User::factory()->create();
        $book = Book::where('user_id', '!=', $user->id)->firstOrFail();

        // 3,4
        $this->actingAs($user)
            ->get(route('books.show', $book))
            ->assertOk()
            ->assertViewIs('books.show')
            ->assertSeeText($book->title)
            ->assertDontSee('href="' . route('books.edit', $book) . '"', false)
            ->assertDontSee('action="' . route('books.destroy', $book) . '"', false);
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
