<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return View('favorites.index', [
            'books' => $request->user()->favoriteBooks()->paginate(10)
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Book $book)
    {
        $message = '';

        $user = $book->favoriteUsers()->find($request->user()->id);
        if ($user) {
            // お気に入り済みなら解除 
            $book->favoriteUsers()->detach($user->id);
            $message = 'お気に入り解除しました。';
        } else {
            // お気に入り未登録なら登録する 
            $book->favoriteUsers()->syncWithoutDetaching($request->user()->id);
            $message = 'お気に入り登録しました。';
        }

        return redirect(route('books.show', ['book' => $book]))
            ->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Book $book)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Book $book)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Book $book)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Book $book)
    {
        //
    }
}
