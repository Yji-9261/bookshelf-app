<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\Review;
use App\Http\Requests\StoreUpdateReviewRequest;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function store(StoreUpdateReviewRequest $request, Book $book)
    {
        $validated = $request->validated();

        // 紐つける書籍IDを付与してレビュー作成
        $validated['book_id'] = $book->id;
        $request->user()->postReviews()->create($validated);

        return redirect(route('books.show', ['book' => $book]))->with('success', 'レビューを投稿しました。');
    }

    /**
     * Display the specified resource.
     */
    public function show(Review $review)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Review $review)
    {
        $this->authorize('update', $review);

        return View('/reviews.edit', ['review' => $review]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreUpdateReviewRequest $request, Review $review)
    {
        $this->authorize('update', $review);
        $review->update($request->validated());

        return redirect(route('books.show', ['book' => $review->book]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Review $review)
    {
        $this->authorize('delete', $review);
        $review->delete();

        return redirect(route('books.show', ['book' => $review->book]))
            ->with('success', 'レビューを削除しました。');
    }
}
