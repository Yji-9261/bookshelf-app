<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Genre;
use App\Http\Requests\StoreUpdateBookRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        return View('books.index', [
            'books' => Book::paginate(10)
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
