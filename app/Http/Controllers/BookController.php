<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Genre;
use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreUpdateBookRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexBookRequest $request): View {
        // title,authorが半角英数字で、スペースなく断続した場合
        // 文字列が他の書籍欄まではみ出してしまう問題を確認
        // バックエンドのみでの対応は難しいことを理由に、仕様とすることをPMと取り決めた

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
                    return $query->whereHas(
                        'genres',
                        fn ($query) => $query->where('genres.id', $genreId)
                    );
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

        return View('books.index', [
            'books' => $books,
            'genres' => Genre::all(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View {
        return View('books.create', [
            'genres' => Genre::all()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUpdateBookRequest $request): RedirectResponse {
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
    public function show(Book $book): View {
        // titleが半角英数字で、スペースなく断続した場合、
        // 文字列が枠外にはみ出し、お気に入りボタンも押下できなくなってしまう問題を確認
        // バックエンドのみでの対応は難しいことを理由に、仕様とすることをPMと取り決めた

        return View('books.show', [
            'book' => $book
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Book $book): View {
        $this->authorize('update', $book);
        return view('books.edit', [
            'book' => $book,
            'genres' => Genre::all()
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreUpdateBookRequest $request, Book $book): RedirectResponse {
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
    public function destroy(Book $book): RedirectResponse {
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
    public function getBookInformation(string $isbn) {
        // 2026/10/21 GoogleBooksApiのisbnを指定した検索に不具合が生じている模様
        // https://issuetracker.google.com/issues/569300353
        // 実際にisbn検索しても0件しか取れないため
        // 代わりにデザインパターン」キーワード検索の結果を取得し、テストした

        $apiKey = env('GOOGLE_BOOK_API_KEY');
        $request = Http::get('https://www.googleapis.com/books/v1/volumes', [
            'q' => "isbn:{$isbn}",
            //'q' => "デザインパターン",
            'maxResults' => 1,
            'key' => $apiKey,
        ]);

        // isbn検索はデータ数は１件で確定しているためitemsの0を指定して配列を取得
        $volumeInfo = $request->json('items.0.volumeInfo');
        return [
            'title' => $volumeInfo['title'],

            // 共著などがあるため配列で複数件返ってくる可能性がある。
            // PMとカンマ区切りとする取り決めとした
            'author' => implode(',', $volumeInfo['authors']),

            'description' => $volumeInfo['description'],
            'image_url' => $volumeInfo['imageLinks']['thumbnail'],
            'published_date' => $volumeInfo['publishedDate'],
        ];
    }
}
