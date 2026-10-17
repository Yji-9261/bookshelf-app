<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\View\View;

class RankingController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return View('ranking.index', [
            'rankedBooks' => $this->getRankedBooks()
        ]);
    }

    /**
     * 書籍一覧をランキング表示に必要に整形して取得する
     * 
     * @return Collection
     */
    private function getRankedBooks(): Collection
    {
        // レビューが存在が存在する、平均評価top10を降順で取得する
        return Book::with('reviews')
            ->withCount('reviews')
            ->has('reviews')
            ->get()
            ->sortByDesc(fn($book) => $book->reviews_avg_rating)
            ->take(10);
    }
}
