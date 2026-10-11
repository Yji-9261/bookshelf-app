<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\Request;

class ReviewLikeController extends Controller
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
    public function store(Request $request, Review $review)
    {
        $message = '';

        $user = $review->likedByUsers()->find($request->user()->id);
        if ($user) {
            // いいね済みなら解除
            $review->likedByUsers()->detach($user->id);
            $message = 'いいねを解除しました。';
        } else {
            // いいね未登録なら登録
            $review->likedByUsers()->syncWithoutDetaching($request->user()->id);
            $message = 'いいねを登録しました。';
        }
        return redirect(route('books.show', ['book' => $review->book]))
            ->with('success', $message);
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
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Review $review)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Review $review)
    {
        //
    }
}
