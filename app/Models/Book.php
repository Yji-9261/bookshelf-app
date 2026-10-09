<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'anthor',
        'isbn',
        'published_date',
    ];

    /**
     * 登録したユーザー
     * @return BelongsTo
     */
    public function registerUser(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 投稿されたレビュー
     * @return HasMany
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * 紐ついているジャンル
     * @return BelongsToMany
     */
    public function genres(): BelongsToMany
    {
        // 中間テーブル名(book_genre)、外部キーは規約通りのため省略
        return $this->belongsToMany(Genre::class)->withTimestamps();
    }

    /**
     * お気に入り
     * @return BelongsToMany
     */
    public function favoriteUsers(): BelongsToMany
    {
        // 外部キーは規約通り名称のため省略
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }
}
