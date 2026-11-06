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
     * このジャンルに紐ついている書籍
     */
    public function books(): BelongsToMany {
        // 中間テーブル名(book_genre)、外部キーは規約通りのため省略
        return $this->belongsToMany(Book::class)->withTimestamps();
    }
}
