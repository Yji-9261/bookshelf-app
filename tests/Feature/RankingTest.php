<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    /** ゲスト・認証ユーザーはランキングページに遷移する */
    public function testRankingLink(): void
    {
        // 1. ゲストとして書籍一覧画面のランキング画面リンクにアクセス
        // 2. テスト: ランキング画面に遷移する
        // 3. テストユーザーを作成する
        // 4. テストユーザーで認証してランキング画面にアクセス
        // 5. テスト: ランキング画面に遷移する

        $rankingUrl = route('ranking.index');

        // 1
        $this->get('/')
            ->assertOk()
            ->assertViewIs('books.index')
            ->assertSee('href="' . $rankingUrl . '"', false);

        // 2
        $this->get($rankingUrl)
            ->assertOk()
            ->assertViewIs('ranking.index');
        $this->assertGuest();

        // 3
        $user = User::factory()->create();
        $this->actingAs($user);

        // 4
        $this->get('/')
            ->assertOk()
            ->assertSee('href="' . $rankingUrl . '"', false);

        // 5
        $this->get($rankingUrl)
            ->assertOk()
            ->assertViewIs('ranking.index');
        $this->assertAuthenticatedAs($user);
    }

    /** ランキングページが正常に表示される */
    public function testRankingView(): void
    {
        // 1. 書籍とレビュー平均点を把握できるようにテストデータ作成
        // 2. ランキング画面にアクセス
        // 3. テスト: レビューがある10冊のタイトル・著者名・平均評価点・レビュー数が表示されている
        // 4. テスト: 平均評価降順に並んでいる

        // 1
        $this->createTestData();

        // レビュー作成時に用いたレビュー評価初期値テーブルを
        // レビュー数と評価平均点の検証用途として加工する
        $averages = collect(self::INITIAL_RATINGS)
            ->map(fn($ratings) => collect($ratings)->avg());

        // top10に入った書籍の登録順インデックスを保持
        $topIndexes = $averages->sortDesc()->take(10)->keys();

        // 評価降順検証用のデータ
        $sortedDescAverages = $averages
            ->sortDesc()
            ->take(10)
            ->values()
            ->all();

        // 2
        $response = $this->get(route('ranking.index'))
            ->assertOk()
            ->assertViewIs('ranking.index');

        // 3
        $rankedBooks = $response->viewData('rankedBooks');
        // 10件の書籍データが渡されているか検証
        $this->assertCount(10, $rankedBooks);

        $document = new \DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $response->getContent());
        $xpath = new \DOMXPath($document);
        $cards = $xpath->query('//a[.//h3]');
        // 10件の書籍が実際に表示されているか検証
        $this->assertCount(10, $cards);

        // 評価降順の検証を行うため、明示的に書籍を登録順に取得する
        $books = Book::orderBy('id')->get();
        foreach ($books as $index => $book) {
            $showUrl = route('books.show', $book);
            $links = $xpath->query('//a[@href="' . $showUrl . '"]');

            // top10に入らなかった書籍が混じっていないか検証
            if (!$topIndexes->contains($index)) {
                $this->assertCount(0, $links);
                continue;
            }
            $this->assertCount(1, $links);
            $card = $links->item(0);

            // タイトル・著者名・評価・レビュー数検証
            $this->assertSame($book->title, $xpath->evaluate('normalize-space(.//h3)', $card));
            $this->assertSame($book->author, $xpath->evaluate('normalize-space(.//p)', $card));
            $this->assertStringContainsString(number_format($averages[$index], 2), $card->textContent);
            $reviewCount = count(self::INITIAL_RATINGS[$index]);
            $this->assertStringContainsString('(' . $reviewCount . '件のレビュー)', $card->textContent);
        }

        // 4
        // DOMのカード順から平均点を取得して比較（同点同士の順番は指定しない）
        $shownAverages = [];
        foreach ($cards as $card) {
            $index = $books->search(fn($book) => route('books.show', $book) === $card->getAttribute('href'));
            $this->assertNotFalse($index);
            $shownAverages[] = $averages[$index];
        }
        $this->assertSame($sortedDescAverages, $shownAverages);
    }

    /** ランキングページが正常に表示される(レビューなし書籍が含まれることがないか) */
    public function testUnreviewedBooksHidden(): void
    {
        // 1. 書籍２冊作成し、片方にレビュー作成
        // 2. ランキング画面にアクセス
        // 3. テスト: レビューがされた書籍１冊のみ表示されているか確認

        // 1
        $user = User::factory()->create();
        $unreviewedBook = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'レビューのない書籍',
        ]);
        $reviewedBook = Book::factory()->create([
            'user_id' => $user->id,
            'title' => 'レビューのある書籍',
        ]);
        $reviewedBook->reviews()->create([
            'user_id' => $user->id,
            'rating' => 4,
            'comment' => 'ランキング表示確認用のレビュー',
        ]);

        // 2,3
        $response = $this->get(route('ranking.index'))
            ->assertOk()
            ->assertViewIs('ranking.index')
            ->assertSeeText($reviewedBook->title)
            ->assertSee('href="' . route('books.show', $reviewedBook) . '"', false)
            ->assertDontSeeText($unreviewedBook->title)
            ->assertDontSee('href="' . route('books.show', $unreviewedBook) . '"', false);

        $rankedBooks = $response->viewData('rankedBooks');
        $this->assertCount(1, $rankedBooks);
        $this->assertTrue($rankedBooks->first()->is($reviewedBook));
    }

    /**
     * ランキング検証用のテストデータを生成
     */
    private function createTestData(): void
    {
        // ユーザー作成
        $user = User::factory()->create();

        // 書籍11件作成
        $books = Book::factory()->count(11)->create(['user_id' => $user->id]);

        // ジャンル紐つけ
        $genre = Genre::create(['name' => '小説']);
        $books->each(function ($book) use ($genre) {
            $book->genres()->sync($genre->id);
        });

        // 書籍11冊に対して、定数の配列と同じ順番でレビュー作成
        $books->each(function ($book, $index) use ($user) {
            foreach (self::INITIAL_RATINGS[$index] as $rating) {
                $book->reviews()->create([
                    'user_id' => $user->id,
                    'rating' => $rating,
                    'comment' => 'テスト',
                ]);
            }
        });
    }

    /**
     * 書籍に対するレビュー評価初期値テーブル。検証用にも用いられる。
     */
    const INITIAL_RATINGS = [
        // 書籍1 レビュー数1 平均レビュー2.0
        [2],
        // 書籍2 レビュー数2 平均レビュー3.0
        [3, 3],
        // 書籍3 レビュー数3 平均レビュー4.0
        [3, 4, 5],
        // 書籍4 レビュー数2 平均レビュー5.0
        [5, 5],
        // 書籍5 レビュー数5 平均レビュー1.2
        [1, 1, 1, 1, 2],
        // 書籍6 レビュー数5 平均レビュー2.4
        [2, 2, 2, 3, 3],
        // 書籍7 レビュー数5 平均レビュー3.6
        [3, 3, 4, 4, 4],
        // 書籍8 レビュー数5 平均レビュー4.8
        [4, 5, 5, 5, 5],
        // 書籍9 レビュー数10 平均レビュー2.7
        [2, 2, 2, 3, 3, 3, 3, 3, 3, 3],
        // 書籍10 レビュー数3 平均レビュー3.0
        [2, 3, 4],
        // 書籍11 レビュー数1 レビュー5.0
        [5],
    ];
}
