<?php

use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BookController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;

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

/** 書籍 */
Route::get('/', [BookController::class, 'index'])
    ->name('books.index');

Route::get('/books', [BookController::class, 'index'])
    ->name('books.index');

Route::middleware(['auth'])->group(function () {
    Route::get('/books/create', [BookController::class, 'create'])
        ->name('books.create');

    Route::post('/books/create', [BookController::class, 'store'])
        ->name('books.store');
});

Route::get('/books/{book}', [BookController::class, 'show'])
    ->name('books.show');

Route::middleware(['auth'])->group(function () {
    Route::get('/books/{book}/edit', [BookController::class, 'edit'])
        ->name('books.edit');

    Route::put('/books/{book}', [BookController::class, 'update'])
        ->name('books.update');

    Route::delete('/books/{book}', [BookController::class, 'destroy'])
        ->name('books.destroy');
});

/** レビュー */
Route::middleware(['auth'])->group(function () {
    Route::post('/books/{book}/reviews', [ReviewController::class, 'store'])
        ->name('reviews.store');

    Route::get('/reviews/{review}/edit', [ReviewController::class, 'edit'])
        ->name('reviews.edit');

    Route::put('/reviews/{review}', [ReviewController::class, 'update'])
        ->name('reviews.update');

    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])
        ->name('reviews.destroy');
});

/** お気に入り */
Route::middleware(['auth'])->group(function () {
    Route::get('/favorites', [FavoriteController::class, 'index'])
        ->name('favorites.index');

    Route::post('/book/{book}/favorites', [FavoriteController::class, 'store'])
        ->name('favorites.toggle');
});

/** いいね */
Route::middleware(['auth'])->group(function () {
    Route::post('/reviews/{review}/like', [ReviewLikeController::class, 'store'])
        ->name('reviews.like');
});

/** ランキング */
Route::get('/ranking', [RankingController::class, 'index'])
    ->name('ranking.index');

/** ジャンル */
Route::middleware(['auth'])->group(function () {
    Route::get('/genres', [GenreController::class, 'index'])
        ->name('genres.index');

    Route::post('/genres', [GenreController::class, 'store'])
        ->name('genres.store');

    Route::get('/genres/create', [GenreController::class, 'create'])
        ->name('genres.create');

    Route::get('/genres/{genre}', [GenreController::class, 'show'])
        ->name('genres.show');

    Route::get('/genres/{genre}/edit', [GenreController::class, 'edit'])
        ->name('genres.edit');

    Route::put('/genres/{genre}', [GenreController::class, 'update'])
        ->name('genres.update');

    Route::delete('/genres/{genre}', [GenreController::class, 'destroy'])
        ->name('genres.destroy');
});


/** レポート */
Route::middleware(['auth'])->group(function () {
    Route::get('/reports', function () { }/*[GenreController::class, 'index']*/)
        ->name('reports.index');
});

/** 読書計画 */
Route::middleware(['auth'])->group(function () {
    Route::get('/reading-plans', function () { }/*[GenreController::class, 'index']*/)
        ->name('reading-plans.index');
});


/** 通知 */
Route::middleware(['auth'])->group(function () {
    Route::get('/notifications', function () { }/*[GenreController::class, 'index']*/)
        ->name('notifications.index');
});

/** isbn検索 */
Route::middleware(['auth'])->group(function () {
    Route::get('/books/isbn/{isbn}', [BookController::class, 'getBookInformation']);
});