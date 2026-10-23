<?php

namespace App\Http\Controllers;

use App\Enums\Sort;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\Request;
use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreUpdateBookRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

use Illuminate\Support\Facades\Log;


class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexBookRequest $request): View
    {
        $validated = $request->validated();

        // reviews_avg_rating(評価平均点)付与
        $books = Book::query()->withAvg('reviews', 'rating')
            // keyword検索
            ->when(
                $validated['keyword'] ?? null,
                function ($query, $keyword) {
                    // whereで囲んでorWhere以降の検索優先順位を保護
                    $query->where(function ($query) use ($keyword) {
                        $query->where('title', 'LIKE', "%{$keyword}%")
                            ->orWhere('author', 'LIKE', "%{$keyword}%");
                    });
                }
            )

            // genre検索
            ->when(
                $validated['genre'] ?? null,
                function ($query, $genreId) {
                    return $query->whereHas('genres', fn($query) => $query->where('genres.id', $genreId));
                }
            )

            // 並び替え
            ->when(
                $validated['sort'] ?? null,
                function ($query, $sort) {
                    return match ($sort) {
                        'newest' => $query->orderBy('created_at', 'desc'),
                        'oldest' => $query->orderBy('created_at', 'asc'),
                        'rating' => $query->withAvg('reviews', 'rating')->orderBy('reviews_avg_rating', 'desc'),
                        'title' => $query->orderBy('title', 'asc'),
                    };
                }
            )
            ->paginate(10)
            ->withQueryString();

        // title,authorが半角英数字でスペースなく断続した場合
        // 文字列が枠外に飛び出てしまう問題に対応
        // bladeには手をつけず、バックエンド側で対応することをPMと取り決めた
        // 追記: 
        // スペースを盛り込むと改行可能
        // そのため仕様として対応したい

        // $books->through(function ($book) {
        //     $book->title = Str::limit($book->title, 25);
        //     $book->author = Str::limit($book->author, 35);
        //     return $book;
        // });

        return View('books.index', [
            'books' => $books,
            'genres' => Genre::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return View('books.create', [
            'genres' => Genre::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUpdateBookRequest $request): RedirectResponse
    {
        $book = DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $book = $request->user()
                ->registeredBooks()
                ->create($validated);

            // 登録済みのジャンルIDが１つ以上はリクエストされるので書籍に紐つけ
            $book->genres()->sync($validated['genres']);
            return $book;
        });

        // 書籍詳細画面にリダイレクト
        return redirect(route('books.show', ['book' => $book]))
            ->with('success', '書籍を登録しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(Book $book): View
    {
        // titleが半角英数字でスペースなく断続した場合
        // 文字列が枠外に飛び出て、お気に入りボタンも押下できなくなってしまう問題に対応
        // bladeには手をつけず、バックエンド側で対応することをPMと取り決めた

        // バックエンド側での対応は困難のため、未対応かもしくはfront側の修正で対応

        return View('books.show', [
            'book' => $book
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);
        return view('books.edit', [
            'book' => $book,
            'genres' => Genre::all()
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreUpdateBookRequest $request, Book $book): RedirectResponse
    {
        $this->authorize('update', $book);

        DB::transaction(function () use ($request, $book) {
            $validated = $request->validated();
            $book->update($validated);

            // 登録済みのジャンルIDが１つ以上はリクエストされるので書籍に紐つけ
            $book->genres()->sync($validated['genres']);
        });

        // 書籍詳細画面にリダイレクト
        return redirect(route('books.show', ['book' => $book]))->with('success', '書籍情報を更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);
        $book->delete();

        // 書籍一覧画面にリダイレクト
        return redirect(route('books.index'))->with('success', '書籍を削除しました。');
    }

    /**
     * 指定のISBNにてGooglBooksAPIにアクセスし、対象書籍の情報をjsに返す処理
     * @param string $isbn
     * @return array
     */
    public function getBookInformation(string $isbn)
    {
        // 2026/10/21 GoogleBooksApiのisbnを指定した検索に不具合が生じている模様
        // https://issuetracker.google.com/issues/569300353
        // 実際にisbn検索しても0件しか取れないため現状検証不可
        // 代わりにLaravelキーワード検索の結果を取得し、テストする

        $apiKey = env('GOOGLE_BOOK_API_KEY');
        $request = Http::get('https://www.googleapis.com/books/v1/volumes', [
            //'q' => "isbn:{$isbn}",
            'q' => "Laravel",
            'maxResults' => 1,
            'key' => $apiKey,
        ]);

        // isbn検索のためデータ数は１件で確定しているためitemsの0を指定して配列を取得
        $volumeInfo = $request->json('items.0.volumeInfo');
        return [
            'title' => $volumeInfo['title'],
            'author' => implode('・', $volumeInfo['authors']),
            'description' => $volumeInfo['description'],
            'image_url' => $volumeInfo['imageLinks']['thumbnail'],
            'published_date' => $volumeInfo['publishedDate'],
        ];
    }
}
