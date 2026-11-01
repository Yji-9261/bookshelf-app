<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Review;
use App\Models\Book;
use App\Models\Genre;
use Database\Seeders\UserSeeder;
use Database\Seeders\GenreSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\ReadingPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    /** ゲストがマイ読書レポート画面にアクセスする */
    public function testGuestCannotAccess(): void {
        $this->get('/reports')->assertRedirect('/login');
    }

    /**
     * 認証ユーザーがーマイ読書レポート画面にアクセスする
     */
    public function testUserCanViewReports(): void {
        $this->actingAs(User::factory()->create())
            ->get('/reports')
            ->assertOk()
            ->assertViewIs('reports.index');
    }


    /**
     * 基本統計が正常に表示されている
     */
    public function testCanViewSummary(): void {
        // 1. シーダーからテストデータを作成し、評価2・4・5のレビューと別ユーザーのレビューを追加する
        // 2. 作成済みのレビュー・読書計画を持つ認証ユーザーでマイ読書レポート画面にアクセスする
        // 3. 基本統計の項目をラベルごとに取得する
        // 4. テスト: 総レビュー数が3と表示される
        // 5. テスト: 読了冊数が1と表示される
        // 6. テスト: 平均評価が小数第1位に丸められた3.7と表示される

        // 1
        $this->createTestDataFromSeeder();
        $user = User::where('email', 'yamada@example.com')->firstOrFail();
        $anotherUser = User::where('email', 'suzuki@example.com')->firstOrFail();
        foreach ([2, 4, 5] as $index => $rating) {
            Review::factory()->create([
                'user_id' => $user->id,
                'book_id' => $index + 1,
                'rating' => $rating,
            ]);
        }
        Review::factory()->create([
            'user_id' => $anotherUser->id,
            'book_id' => 1,
            'rating' => 1,
        ]);

        // 2
        $response = $this->actingAs($user)->get('/reports')
            ->assertOk()
            ->assertViewIs('reports.index');

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $summaries = $xpath->query('//div[h3[normalize-space(.)="基本統計"]]');
        $this->assertCount(1, $summaries);

        // 4,5,6: 他の箇所の数字ではなく、各ラベルに対応する表示値を完全一致で確認する
        foreach (['総レビュー数' => '3', '読了冊数' => '1', '平均評価' => '3.7'] as $label => $expected) {
            $values = $xpath->query(
                './/div[div[normalize-space(.)="' . $label . '"]]/div[1]',
                $summaries->item(0),
            );
            $this->assertCount(1, $values);
            $this->assertSame($expected, trim($values->item(0)->textContent), $label);
        }
    }

    /**
     * 評価分布が正しく行われている
     */
    public function testRatingDistribution(): void {
        // 1. 評価1→1件、評価2→2件、評価3→3件、評価4→4件、評価5→5件のテストデータ作成
        // 2. 作成済みのレビューを持つ認証ユーザーでマイ読書レポート画面にアクセスする
        // 3. 評価分布の項目を取得する
        // 4. テスト: 評価ごとのレビュー数が一致しているか確認

        // 1
        $user = User::factory()->create();
        foreach (range(1, 5) as $rating) {
            foreach (range(1, $rating) as $number) {
                Review::factory()->create([
                    'user_id' => $user->id,
                    'book_id' => Book::factory()->create()->id,
                    'rating' => $rating,
                ]);
            }
        }
        // 他ユーザーのレビューが集計に含まれないことも確認する
        Review::factory()->create([
            'user_id' => User::factory()->create()->id,
            'book_id' => Book::factory()->create()->id,
            'rating' => 1,
        ]);

        // 2
        $response = $this->actingAs($user)->get('/reports')
            ->assertOk()
            ->assertViewIs('reports.index');

        // 3
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $sections = $xpath->query('//div[h3[normalize-space(.)="評価分布"]]');
        $this->assertCount(1, $sections);
        $rows = $xpath->query('./div/div', $sections->item(0));
        $this->assertCount(5, $rows);

        // 4: 各行の星の数と件数を完全一致で比較する
        foreach (range(1, 5) as $rating) {
            $row = $rows->item($rating - 1);
            $this->assertSame(
                str_repeat('★', $rating),
                $xpath->evaluate('normalize-space(./div[1]/span)', $row),
            );
            $this->assertSame(
                $rating . '件',
                $xpath->evaluate('normalize-space(./div[3])', $row),
            );
        }
    }

    /**
     * 高評価書籍 TOP5が評価4以上のtop5が正しく表示されている
     */
    public function testTopRatedBooks(): void {
        // 1. 評価5の書籍を2冊、評価4の書籍を2冊、評価1~3の書籍を1冊ずつ作成
        // 2. 他ユーザーの評価5の書籍を作成する
        // 3. 作成済みのレビューを持つ認証ユーザーでマイ読書レポート画面にアクセスする
        // 4. 高評価書籍 TOP5の項目を取得する
        // 5. テスト: 認証ユーザーがレビューを行った、評価4と5のTOP4の書籍が評価降順に表示されている
        // 6. 認証ユーザーが評価5の書籍を1冊、評価4の書籍を1冊作成
        // 7. テスト: 評価5の書籍が3冊、評価4の書籍が2冊が評価降順に表示されている

        // 1
        $user = User::factory()->create();
        $ratingsByBook = [];
        // 書籍登録順をまばらにして、評価降順であることを検証する
        foreach ([4, 1, 5, 2, 4, 3, 5] as $rating) {
            $book = Book::factory()->create();
            Review::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'rating' => $rating,
            ]);
            if ($rating >= 4) {
                $ratingsByBook[$book->id] = $rating;
            }
        }
        // 2
        Review::factory()->create([
            'user_id' => User::factory()->create()->id,
            'book_id' => Book::factory()->create()->id,
            'rating' => 5,
        ]);

        // 2,3,4
        $response = $this->actingAs($user)->get('/reports')
            ->assertOk()
            ->assertViewIs('reports.index');
        $this->assertTopRatedBooks($response, $ratingsByBook, [5, 5, 4, 4]);

        // 5
        foreach ([5, 4] as $rating) {
            $book = Book::factory()->create();
            Review::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'rating' => $rating,
            ]);
            $ratingsByBook[$book->id] = $rating;
        }

        // 6
        $response = $this->get('/reports')->assertOk();
        $this->assertTopRatedBooks($response, $ratingsByBook, [5, 5, 5, 4, 4]);
    }

    /** 高評価書籍欄の件数・対象書籍・評価順・順位・表示内容を確認する */
    private function assertTopRatedBooks(
        \Illuminate\Testing\TestResponse $response,
        array $ratingsByBook,
        array $expectedRatings,
    ): void {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $sections = $xpath->query('//div[h3[normalize-space(.)="高評価書籍 TOP5"]]');
        $this->assertCount(1, $sections);
        $cards = $xpath->query('./div/a', $sections->item(0));
        $this->assertCount(count($expectedRatings), $cards);

        $seenIds = [];
        foreach ($cards as $index => $card) {
            $matchingIds = array_filter(
                array_keys($ratingsByBook),
                fn ($id) => route('books.show', $id) === $card->getAttribute('href'),
            );
            $this->assertCount(1, $matchingIds, '認証ユーザーの評価4以上の書籍であること');
            $id = array_values($matchingIds)[0];
            $this->assertNotContains($id, $seenIds);
            $seenIds[] = $id;
            $this->assertSame($expectedRatings[$index], $ratingsByBook[$id]);

            $book = Book::findOrFail($id);
            $this->assertSame((string) ($index + 1), $xpath->evaluate('normalize-space(./div[1])', $card));
            $this->assertSame($book->title, $xpath->evaluate('normalize-space(./div[2]/div[1])', $card));
            $this->assertSame($book->author, $xpath->evaluate('normalize-space(./div[2]/div[2])', $card));
            $rating = $expectedRatings[$index];
            $this->assertSame(
                str_repeat('★', $rating) . str_repeat('☆', 5 - $rating),
                $xpath->evaluate('normalize-space(./div[3])', $card),
            );
        }
    }


    /**
     * ジャンル別評価傾向 TOP5が正しく表示されている
     */
    public function testGenreRatings(): void {
        // 1. ジャンル1→書籍1・2・3・4、ジャンル2→書籍2、ジャンル3→書籍3、ジャンル4→書籍4
        // 2. 書籍1→評価1、書籍2→評価2、書籍3→評価3、書籍4→評価4
        // 3. 作成済みのレビューを持つ認証ユーザーでマイ読書レポート画面にアクセスする
        // 4. ジャンル別評価傾向 TOP5の項目を取得する
        // 5. テスト: ジャンル4,ジャンル3、ジャンル1、ジャンル2の順に表示される
        // 6. ジャンル5→書籍1 、ジャンル6→書籍1・２
        // 7. テスト: ジャンル4,ジャンル3、ジャンル1、ジャンル2、ジャンル6の順に表示される

        // 1,2
        $user = User::factory()->create();
        $books = Book::factory()->count(4)->create();
        $genres = collect();
        foreach (range(1, 4) as $number) {
            $genres->put($number, Genre::create(['name' => 'ジャンル' . $number]));
        }
        $genres[1]->books()->attach($books->modelKeys());
        $genres[2]->books()->attach($books[1]->id);
        $genres[3]->books()->attach($books[2]->id);
        $genres[4]->books()->attach($books[3]->id);
        foreach ($books as $index => $book) {
            Review::factory()->create([
                'user_id' => $user->id,
                'book_id' => $book->id,
                'rating' => $index + 1,
            ]);
        }

        // 3,4,5
        $expected = [
            ['genre' => $genres[4], 'count' => 1, 'average' => '4.0'],
            ['genre' => $genres[3], 'count' => 1, 'average' => '3.0'],
            ['genre' => $genres[1], 'count' => 4, 'average' => '2.5'],
            ['genre' => $genres[2], 'count' => 1, 'average' => '2.0'],
        ];
        $response = $this->actingAs($user)->get('/reports')
            ->assertOk()
            ->assertViewIs('reports.index');
        $this->assertGenreRatingCards($response, $expected);

        // 6
        $genres->put(5, Genre::create(['name' => 'ジャンル5']));
        $genres->put(6, Genre::create(['name' => 'ジャンル6']));
        $genres[5]->books()->attach($books[0]->id);
        $genres[6]->books()->attach([$books[0]->id, $books[1]->id]);

        // 7
        $expected[] = ['genre' => $genres[6], 'count' => 2, 'average' => '1.5'];
        $response = $this->get('/reports')->assertOk();
        $this->assertGenreRatingCards($response, $expected);
    }

    /** ジャンル別評価欄の各カードを、指定順に完全一致で検証する */
    private function assertGenreRatingCards(
        \Illuminate\Testing\TestResponse $response,
        array $expected,
    ): void {
        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $sections = $xpath->query('//div[h3[normalize-space(.)="ジャンル別評価傾向 TOP5"]]');
        $this->assertCount(1, $sections);
        $cards = $xpath->query('./div/a', $sections->item(0));
        $this->assertCount(count($expected), $cards);

        foreach ($expected as $index => $item) {
            $card = $cards->item($index);
            $this->assertSame(route('genres.show', $item['genre']), $card->getAttribute('href'));
            $this->assertSame((string) ($index + 1), $xpath->evaluate('normalize-space(./div[1])', $card));
            $this->assertSame($item['genre']->name, $xpath->evaluate('normalize-space(./div[2]/div[1])', $card));
            $this->assertSame($item['count'] . '件のレビュー', $xpath->evaluate('normalize-space(./div[2]/div[2])', $card));
            $this->assertSame($item['average'], $xpath->evaluate('normalize-space(./div[3]/div[1])', $card));
        }
    }

    private function createTestDataFromSeeder() {
        $this->seed([
            UserSeeder::class,
            GenreSeeder::class,
            BookSeeder::class,
            ReadingPlanSeeder::class,
        ]);
    }
}
