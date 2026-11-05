<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;

class RankingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View {
        return View('ranking.index', [
            'rankedBooks' => $this->getRankedBooks(),
        ]);
    }

    /**
     * レビューのある書籍を平均評価の降順で最大10件取得する。
     * 各書籍に平均評価とレビュー件数を付与する。
     * 平均評価と件数の高い順に並び替え。
     *
     * @return Collection<int, Book>
     */
    private function getRankedBooks(): Collection {
        return
            Book::withAvg('reviews', 'rating')
                ->withCount('reviews')
                ->has('reviews')
                ->orderByDesc('reviews_avg_rating')
                ->orderByDesc('reviews_count')
                ->limit(10)
                ->get();
    }
}
