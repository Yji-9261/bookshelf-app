<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreUpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexBookRequest $request): View {
        $validated = $request->validated();

        // reviews_avg_rating(評価平均点)付与
        $books = Book::query()
            ->with('genres')
            ->withAvg('reviews', 'rating')

            // keyword検索
            ->when(
                // キーワードに0が指定されても実行できるようにstrに変換
                isset($validated['keyword']) ? str($validated['keyword']) : null,
                function ($query, $keyword) {
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
                        'newest' => $query->orderBy('created_at', 'desc')->orderBy('id', 'desc'),
                        'oldest' => $query->orderBy('created_at', 'asc')->orderBy('id', 'asc'),
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
            'genres' => Genre::all(),
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
        $book->load([
            'genres',
            'reviews.user',
            'reviews.likedByUsers',
        ]);

        return View('books.show', [
            'book' => $book,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Book $book): View {
        $this->authorize('update', $book);

        return view('books.edit', [
            'book' => $book,
            'genres' => Genre::all(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreUpdateBookRequest $request, Book $book): RedirectResponse {
        DB::transaction(function () use ($request, $book) {
            $this->authorize('update', $book);

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
     * 指定のISBNにてGooglBooksAPIにアクセスし、対象書籍の情報、またはエラーメッセージをjsに返す
     *
     * @param  string  $isbn  13桁の半角数字
     * @return array
     */
    public function getBookInformation(string $isbn): array|JsonResponse {
        // 正常時のHTTPステータスコード
        $statusCode = 200;
        $apiKey = config('services.google.books_api_key');

        try {
            $request = Http::get('https://www.googleapis.com/books/v1/volumes', [
                'q' => "isbn:{$isbn}",
                'maxResults' => 1,
                'key' => $apiKey,
            ]);

            // 400~599のエラー発生時
            if ($request->failed()) {
                // js側ではstatusCodeは扱わないので、200以外ならなんでもよい
                // HTTPエラーとして500を返すこととする
                $statusCode = 500;
            }
        } catch (ConnectionException $e) {
            // js側ではstatusCodeは扱わないので、200以外ならなんでもよいが
            // 通信エラーとして502を返すこととする
            $statusCode = 502;
        }

        if ($statusCode !== 200) {
            return response()->json([
                'error' => '通信エラーが発生しました。',
            ], $statusCode);
        }

        // isbn検索はデータ数は１件で確定しているためitemsの0を指定して配列を取得
        $volumeInfo = $request->json('items.0.volumeInfo');

        return [
            'title' => $volumeInfo['title'] ?? '',

            // 共著などがあるため配列で複数件返ってくる可能性がある。
            // PMとカンマ区切りとする取り決めをした
            'author' => implode(',', $volumeInfo['authors'] ?? []),

            'description' => $volumeInfo['description'] ?? '',
            'image_url' => $volumeInfo['imageLinks']['thumbnail'] ?? '',
            'published_date' => $volumeInfo['publishedDate'] ?? '',
        ];
    }
}
