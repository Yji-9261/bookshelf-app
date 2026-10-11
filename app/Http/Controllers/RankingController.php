<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;
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
        // 平均評価top10を降順で取得する
        $books = Book::with('reviews')
            ->limit(10)
            ->get()
            ->sortByDesc(fn($book) => $book->reviews_avg_rating);

        return $books;
    }
}
