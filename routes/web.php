<?php

use GuzzleHttp\Middleware;
use Illuminate\Support\Facades\Route;
use \Illuminate\Database\Eloquent\Collection;
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

Route::get('/', function () {
    return view('books.index', ['books' => new Collection()]);
})->name('books.index');
Route::get('/books', function () {
    return redirect('/');
})->name('books.index');


Route::middleware(['auth'])->group(function () {
    Route::get('/books/create', function () {
        return view('books.create');
    })->name('books.create');

    Route::get('/ranking', function () {
        return view('ranking.index');
    })->name('ranking.index');

    Route::get('/favorites', function () {
        return view('favorites.index');
    })->name('favorites.index');

    Route::get('/genres', function () {
        return view('genres.index');
    })->name('genres.index');
});

