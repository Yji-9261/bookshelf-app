<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BookResource;
use App\Http\Requests\Api\V1\IndexBookRequest;
use App\Http\Requests\Api\V1\StoreUpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;

use Illuminate\Support\Facades\DB;

use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\JsonResponse;

class BookController extends Controller
{
    /**
     * Display a listing of the resource.
     * 
     * @param IndexBookRequest $request
     * @return AnonymousResourceCollection
     */
    public function index(IndexBookRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        // ページネーションデフォルトは20
        $per_page = $validated['per_page'] ?? 20;

        $books = Book::query()
            // reviewsのeager loadingはレビュー評価平均点算出のため使用される
            ->with(['genres', 'reviews'])
            // レビュー評価平均点以外にレビュー数も取得したいのでwithCountする
            ->withCount(['reviews'])

            // キーワード検索（タイトル・著者名への部分一致）
            ->when(
                $validated['keyword'] ?? null,
                function ($query, $keyword) {
                    return $query->where('title', 'LIKE', "%{$keyword}%")
                        ->orWhere('author', 'LIKE', "%{$keyword}%");
                }
            )

            // ジャンル検索
            ->when(
                $validated['genre'] ?? null,
                function ($query, $genre) {
                    return $query->whereHas('genres', fn($query) => $query->where('name', $genre));
                }
            )
            ->paginate($per_page)
            ->withQueryString();

        return BookResource::collection($books);
    }

    /**
     * Store a newly created resource in storage.
     * 
     * @param StoreUpdateBookRequest $request
     * @return JsonResponse
     */
    public function store(StoreUpdateBookRequest $request): JsonResponse
    {
        $book = DB::transaction(function () use ($request) {
            $validated = $request->validated();

            // ※基本時ユーザーID仮で付与。応用時にsanctum認証ユーザーに変更
            $validated['user_id'] = intval($validated['user_id']);

            // firstOrCreateは$validated['genres']のせいで失敗するのでcreateを使用
            // フォームリクエストでunique属性の重複を弾いているのでcreateで問題ない
            $book = Book::create($validated);

            // ジャンルは名称で返ってくるためid変換してから紐つけ
            $ids = Genre::whereIn('name', $validated['genres'])
                ->latest()
                ->pluck('id');
            $book->genres()->syncWithoutDetaching($ids);

            return $book;
        });

        $book->load('genres')->loadCount('reviews');

        return (new BookResource($book))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     * 
     * @param Book $book
     * @return BookResource
     */
    public function show(Book $book): BookResource
    {
        $book->load('genres', 'reviews.user')->loadCount('reviews');
        return new BookResource($book);
    }

    /**
     * Update the specified resource in storage.
     * 
     * @param StoreUpdateBookRequest $request
     * @param Book $book
     * @return BookResource
     */
    public function update(StoreUpdateBookRequest $request, Book $book): BookResource
    {
        DB::transaction(function () use ($request, $book) {
            $validated = $request->validated();
            $genreIds = Genre::whereIn('name', $validated['genres'])->pluck('id');
            unset($validated['genres']);
            $book->update($validated);
            $book->genres()->sync($genreIds);
        });
        $book->load('genres', 'reviews.user')->loadCount('reviews');
        return new BookResource($book);
    }

    /**
     * Remove the specified resource from storage.
     * 
     * @param Book $book
     * @return JsonResponse
     */
    public function destroy(Book $book): JsonResponse
    {
        $book->delete();
        return response()->json(null, 204);
    }
}
