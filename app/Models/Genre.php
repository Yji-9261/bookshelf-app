<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * ジャンル
     * @return BelongsToMany
     */
    public function books(): BelongsToMany
    {
        // 中間テーブル名(book_genre)、外部キーは規約通りのため省略
        return $this->belongsToMany(Book::class)->withTimestamps();
    }

    /**
     * ジャンル・ユーザーごとのレビュー情報を算出する
     * @param int $userId　ユーザーID
     * @return array
     */
    public function calcReviewStatsForUser(int $userId)
    {
        $count = 0;
        $sum = 0;
        foreach ($this->books as $book) {
            // 1書籍に対しユーザー1レビューのためfirstにしてもよさそう
            $reviews = $book->reviews()->where('user_id', $userId)->get();
            $sum += $reviews->sum('rating');
            $count += $reviews->count();
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'count' => $count,
            'average_rating' => $count === 0 ? 0 : $sum / $count,
        ];
    }
}
