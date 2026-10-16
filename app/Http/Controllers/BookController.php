<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Genre;
use App\Http\Requests\StoreUpdateBookRequest;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        // 平均評価点算出のため書籍に紐ついたレビューをEager loading
        $books = Book::with('reviews')->paginate(10);

        // title,authorが半角英数字でスペースなく断続した場合
        // 文字列が枠外に飛び出てしまう問題に対応
        // bladeには手をつけず、バックエンド側で対応することをPMと取り決めた

        // 全角や半角でも適切にスペースを盛り込むと改行可能。
        // そのため仕様として対応したい

        // $books->through(function ($book) {
        //     $book->title = Str::limit($book->title, 25);
        //     $book->author = Str::limit($book->author, 35);
        //     return $book;
        // });

        return View('books.index', [
            'books' => $books
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
}
