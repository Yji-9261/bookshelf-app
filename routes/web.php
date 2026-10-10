<?php

use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Route;
use \Illuminate\Database\Eloquent\Collection;
use App\Http\Controllers\BookController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [BookController::class, 'index'])
    ->name('books.index');
Route::get('/books', function () {
    return redirect('/');
})->name('books.index');

Route::get('/books/create', [BookController::class, 'create'])
    ->middleware(['auth'])
    ->name('books.create');

Route::post('/books/create', [BookController::class, 'store'])
    ->middleware(['auth'])
    ->name('books.store');

Route::get('/books/{book}', [BookController::class, 'show'])
    ->name('books.show');

Route::get('/books/{book}/edit', [BookController::class, 'edit'])
    ->middleware(['auth'])
    ->name('books.edit');

Route::put('/books/{book}', [BookController::class, 'update'])
    ->middleware(['auth'])
    ->name('books.update');

Route::delete('/books/{book}', [BookController::class, 'destroy'])
    ->middleware(['auth'])
    ->name('books.destroy');


Route::middleware(['auth'])->group(function () {
    //    Route::get('/books/create', [BookController::class, 'create'])->name('books.create');


    Route::get('/ranking', function () {
        return view('ranking.index');
    })->name('ranking.index');

    Route::get('/favorites', function () {
        return view('favorites.index');
    })->name('favorites.index');

    Route::get('/genres', function () {
        return view('genres.index');
    })->name('genres.index');

    // お気に入り登録
    Route::post('/books/{book}/favorites', function ($book) {
        return view('genres.index');
    })->name('favorites.toggle');

    // レビュー投稿
    Route::post('/books/{book}/reviews', function () {
        return view('genres.index');
    })->name('reviews.store');

    // いいね
    Route::post('/reviews/{review}/like', function () {
        return view('genres.index');
    })->name('reviews.like');
});

