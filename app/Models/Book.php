<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
    ];

    protected $casts = [
        'published_date' => 'date',
    ];

    /**
     * この書籍を登録したユーザー
     */
    public function registerUser(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * この書籍に投稿されたレビュー
     */
    public function reviews(): HasMany {
        return $this->hasMany(Review::class);
    }

    /**
     * この書籍に紐ついているジャンル
     */
    public function genres(): BelongsToMany {
        // 中間テーブル名(book_genre)、外部キーは規約通りのため省略
        return $this->belongsToMany(Genre::class)->withTimestamps();
    }

    /**
     * この書籍をお気に入り登録したユーザー
     */
    public function favoriteUsers(): BelongsToMany {
        // 外部キーは規約通り名称のため省略
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /**
     * この書籍を対象とする読書計画
     */
    public function readingPlans(): HasMany {
        return $this->hasMany(ReadingPlan::class);
    }
}
