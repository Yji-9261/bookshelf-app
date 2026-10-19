<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
{
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Review $review)
    {
        $user = $review->likedByUsers()->find($request->user()->id);
        if ($user) {
            // いいね済みなら解除
            $review->likedByUsers()->detach($user->id);
        } else {
            // いいね未登録なら登録
            $review->likedByUsers()->syncWithoutDetaching($request->user()->id);
        }
        return redirect(route('books.show', ['book' => $review->book]));
    }
}
