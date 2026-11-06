<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View {
        // レビューを評価降順に並び替えて取得
        // bookとbook.genresをeager loading
        $reviews = $request->user()->postReviews()
            ->with('book.genres')
            ->orderByDesc('rating')
            ->get();

        return view('reports.index', [
            'stats' => [
                'summary' => $this->getSummary($request, $reviews),
                'rating_distribution' => $this->getRatingDistribution($reviews),
                'top_rated_books' => $this->getTopRatedBooks($reviews),
                'genre_ratings' => $this->getGenreRating($reviews),
            ],
        ]);
    }

    /**
     * 基本サマリーの取得
     *
     * @param  Collection  $reviews  評価降順に並び替えられたレビューモデルコレクション
     */
    private function getSummary(
        Request $request,
        Collection $reviews
    ): array {
        return [
            // 総レビュー数
            'total_reviews' => $reviews->count(),

            // 読了冊数
            'books_read' => $request->user()->readingPlans()
                ->whereNotNull('completed_at')
                ->count(),

            // 平均評価
            'average_rating' => $reviews->avg('rating'),
        ];
    }

    /**
     * 認証ユーザが投稿したレビューの評価値分布を取得する
     *
     * @param  Collection  $reviews  評価降順に並び替えられたレビューモデルコレクション
     */
    private function getRatingDistribution(
        Collection $reviews
    ): \Illuminate\Support\Collection {
        // 1~5の評価値ごとのカウント数を算出
        $countBy = $reviews->countBy('rating');

        $distributions = collect();
        foreach (range(1, 5) as $index) {
            // 対象評価値なしだと空になってしまうため
            // 0で表示されるように変換
            $distributions[$index - 1] = $countBy->get($index, 0);
        }

        return $distributions;
    }

    /**
     * 高評価書籍Top5を取得
     *
     * @param  Collection  $reviews  評価降順に並び替えられたレビューモデルコレクション
     */
    private function getTopRatedBooks(
        Collection $reviews
    ): array {
        // $reviewsはあらかじめ評価降順に並び替え、bookをeager loadingしてある

        return $reviews
            // レビュー評価4以上のものを5つ取得
            ->filter(fn ($review) => $review->rating >= 4)
            ->take(5)

            // 評価表示用としてratingキーを付与する
            ->map(function ($review) {
                $book = $review->book;
                $book['rating'] = $review->rating;

                return $book;
            })
            ->toArray();
    }

    /**
     * ジャンルごとに認証ユーザーの平均評価を算出し、平均評価降順にTop5を取得する
     */
    private function getGenreRating(
        Collection $reviews
    ): \Illuminate\Support\Collection {
        return $reviews
            // 書籍ごとのレビューを、ジャンルごとの評価データへフラットに展開する
            ->flatMap(function ($review) {
                return $review->book->genres
                    ->map(function ($genre) use ($review) {
                        return [
                            'genre_id' => $genre->id,
                            'genre_name' => $genre->name,
                            'rating' => $review->rating,
                        ];
                    });
            })
            // ジャンルIDごとに分類
            ->groupBy('genre_id')

            // レビュー数とレビュー評価平均を算出し、blade向けに加工
            ->map(function ($values) {
                return [
                    'id' => $values[0]['genre_id'],
                    'name' => $values[0]['genre_name'],
                    'count' => $values->count(),
                    'average_rating' => $values->avg('rating'),
                ];
            })
            // 平均評価と件数の高い順に並び替え
            ->sortBy([['average_rating', 'desc'], ['count', 'desc']])
            ->take(5)
            // 表示順に0からキーを振り直す
            ->values();
    }
}
