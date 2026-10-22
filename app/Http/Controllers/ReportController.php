<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        // レビューを評価降順に並び替えて取得
        $reviews = $request->user()->postReviews()
            ->orderByDesc('rating')
            ->get();

        return view('reports.index', [
            'stats' => [
                'summary' => $this->getSummary($reviews),
                'rating_distribution' => $this->getRatingDistribution($reviews),
                'top_rated_books' => $this->getTopRatedBooks($reviews),
                'genre_ratings' => $this->getGenreRating($request),
            ]
        ]);
    }

    /**
     * 基本サマリーの取得
     * 
     * @param Collection $reviews
     * @return array
     */
    private function getSummary(Collection $reviews)
    {
        return [
            'total_reviews' => $reviews->count(),
            'books_read' => 0,
            'average_rating' => $reviews->avg('rating'),
        ];
    }

    /**
     * 認証ユーザが投稿したレビューの評価値分布を取得する
     * @param Collection $reviews
     */
    private function getRatingDistribution(Collection $reviews)
    {
        // 1~5の評価値ごとのカウント数を算出
        $countBy = $reviews->countBy('rating');

        // 存在しない評価値だと空になってしまうため0で表示されるように変換
        $distributions = collect();
        foreach (range(1, 5) as $index) {
            // blade側でindex+1で評価値を作成しているので0~4のkeyで作成する
            $distributions[$index - 1] = $countBy->get($index, 0);
        }
        return $distributions;
    }

    /**
     * 高評価書籍Top5を取得
     * @param Collection $reviews
     * @return array
     */
    private function getTopRatedBooks(Collection $reviews)
    {
        /** $reviewsはあらかじめ評価降順に並び替えてある */

        return $reviews
            // レビュー評価4以上のものを5つ取得 
            ->filter(fn($review) => $review->rating >= 4)

            // 評価表示用としてratingキーを付与する
            ->map(function ($review) {
                $book = $review->book;
                $book['rating'] = $review->rating;
                return $book;
            })
            ->take(5)
            ->toArray();
    }

    /**
     * ジャンルごとに認証ユーザーの平均評価を算出し、平均評価降順にTop5を取得する
     * @param Request $request
     */
    private function getGenreRating(Request $request)
    {
        // calcReviewAverageForUser内部でbooksアクセスするのでEagerLoading
        return Genre::with('books.reviews')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($genre) use ($request) {
                return collect(
                    $genre->calcReviewStatsForUser($request->user()->id)
                );
            })
            // 評価なしは表示しない仕様のためフィルタリング
            ->filter(fn($result) => $result['count'] > 0)
            // 平均評価と件数の高い順
            ->sortBy([['average_rating', 'desc'], ['count', 'desc']])
            ->take(5)
            // 表示順に0からキーを振り直す
            ->values();
    }
}
