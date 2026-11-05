<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'user_id',
        'target_date',
        'status',
        'completed_at',
    ];

    protected $casts = [
        'target_date' => 'datetime',
        'completed_at' => 'datetime',
        'status' => ReadingPlanStatus::class,
    ];

    /**
     * アクティブな読書計画レコードを取得する
     */
    public function scopeActive(Builder $query): Builder {
        return $query->whereNull('completed_at');
    }

    /**
     * この読書計画を持つユーザー
     */
    public function user(): BelongsTo {
        return $this->belongsTo(User::class);
    }

    /**
     * この読書計画に紐つく書籍
     */
    public function book(): BelongsTo {
        return $this->belongsTo(Book::class);
    }
}
