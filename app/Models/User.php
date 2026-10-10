<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * 登録した書籍
     * @return HasMany
     */
    public function registeredBooks(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * 投稿したレビュー
     * @return HasMany
     */
    public function postReviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * レビューに対するいいね
     * @return BelongsToMany
     */
    public function likedReviews(): BelongsToMany
    {
        // 外部キーはuser_idとreview_idで規約通りのため省略
        return $this->belongsToMany(Review::class, 'review_likes')->withTimestamps();
    }

    /**
     * お気に入り書籍
     * @return BelongsToMany
     */
    public function favoriteBooks(): BelongsToMany
    {
        // 外部キーはuser_idとbook_idで規約通りのため省略
        return $this->belongsToMany(Book::class, 'favorites')->withTimestamps();
    }
}
