<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'isbn' => $this->isbn,

            // 応用編からnull許容
            'published_date' => $this->published_date?->toDateString(),

            'image_url' => $this->image_url,
            'description' => $this->description,
            'genres' => GenreResource::collection($this->whenLoaded('genres')),

            // nullでもいいが 0 にしておいたほうが安全のため0にする
            'reviews_avg_rating' => $this->reviews_avg_rating ?? 0,

            'reviews_count' => $this->reviews_count,
            'reviews' => ReviewResource::collection($this->whenLoaded('reviews')),
        ];
    }
}
