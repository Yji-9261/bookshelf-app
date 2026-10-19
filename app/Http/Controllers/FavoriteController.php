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
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Book $book)
    {
        $user = $book->favoriteUsers()->find($request->user()->id);
        if ($user) {
            // お気に入り済みなら解除
            $book->favoriteUsers()->detach($user->id);
        } else {
            // お気に入り未登録なら登録する
            $book->favoriteUsers()->syncWithoutDetaching($request->user()->id);
        }

        return redirect(route('books.show', ['book' => $book]));
    }
}
