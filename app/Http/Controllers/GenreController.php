<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUpdateGenreRequest;
use App\Models\Genre;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class GenreController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View {
        return view('genres.index', [
            'genres' => Genre::withCount('books')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View {
        return View('genres.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUpdateGenreRequest $request): RedirectResponse {
        $validated = $request->validated();
        Genre::firstOrCreate($validated);

        // 書籍一覧画面にリダイレクト
        return redirect(route('genres.index'))->with('success', 'ジャンルを作成しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(Genre $genre): View {
        return View('genres.show', [
            'genre' => $genre,
            'books' => $genre->books()->paginate(10),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Genre $genre): View {
        return View('genres.edit', ['genre' => $genre]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreUpdateGenreRequest $request, Genre $genre): RedirectResponse {
        $validated = $request->validated();
        $genre->update($validated);

        // 書籍一覧画面にリダイレクト
        return redirect(route('genres.index'))->with('success', 'ジャンルを更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Genre $genre): RedirectResponse {
        $stateMessage = [];

        if ($genre->books()->exists()) {
            $stateMessage = ['error' => 'このジャンルには書籍が紐付いているため削除できません。'];
        } else {
            $genre->delete();
            $stateMessage = ['success' => 'ジャンルを削除しました。'];
        }

        // 書籍一覧画面にリダイレクト
        return redirect(route('genres.index'))->with($stateMessage);
    }
}
