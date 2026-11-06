<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ReviewController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreUpdateReviewRequest $request, Book $book): RedirectResponse {
        // 同一ユーザーが、同一書籍に対してレビューを既に投稿済みなら、エラーメッセージ表示
        if ($book->reviews()->where('user_id', $request->user()->id)->exists()) {
            return redirect(route('books.show', ['book' => $book]))
                ->with('error', 'この書籍に対して既にレビュー投稿済みです。');
        }

        $validated = $request->validated();

        // 紐つける書籍IDを付与してレビュー作成
        $validated['book_id'] = $book->id;
        $request->user()->postReviews()->create($validated);

        return redirect(route('books.show', ['book' => $book]))
            ->with('success', 'レビューを投稿しました。');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Review $review): View {
        $this->authorize('update', $review);

        return View('reviews.edit', ['review' => $review]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(StoreUpdateReviewRequest $request, Review $review): RedirectResponse {
        $this->authorize('update', $review);
        $review->update($request->validated());

        return redirect(route('books.show', ['book' => $review->book]))
            ->with('success', 'レビューを更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Review $review): RedirectResponse {
        $this->authorize('delete', $review);
        $review->delete();

        return redirect(route('books.show', ['book' => $review->book]))
            ->with('success', 'レビューを削除しました。');
    }
}
