<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,
            'published_date' => $this->published_date,
            'image_url' => $this->image_url,
            'description' => $this->description,
            'genres' => GenreResource::collection($this->whenLoaded('genres')),
            'reviews_avg_rating' => $this->reviews_avg_rating,
            'reviews_count' => $this->reviews_count,
            'reviews' => $this->getReviews($request)
        ];
    }

    /**
     * レビュー表示制御
     *
     * @param Request $request
     * @return mixed|\Illuminate\Http\Resources\MissingValue
     */
    private function getReviews(Request $request)
    {
        // 一覧表示以外の詳細・登録時に出力
        return $this->when(
            !$request->routeIs('books.index'),
            ReviewResource::collection($this->whenLoaded('reviews'))
        );
    }
}
