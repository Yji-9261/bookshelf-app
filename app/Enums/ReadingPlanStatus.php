<?php

namespace App\Enums;

enum ReadingPlanStatus: string
{
    /**
     * 読書状態「読書中」
     */
    case InProgress = 'in_progress';

    /**
     * 読書状態「読了」
     */
    case Completed = 'completed';

    /**
     * 読書状態「期限切れ」
     */
    case Expired = 'expired';

    /**
     * 読書状態ごとの表示名を返す
     */
    public function label(): string {
        return match ($this) {
            self::InProgress => '読書中',
            self::Completed => '読了',
            self::Expired => '期限切れ',
        };
    }

    /**
     * 読書状態ごとのバッジ背景色のCSSを返す
     */
    public function badgeClass(): string {
        return match ($this) {
            self::InProgress => 'bg-blue-100',
            self::Completed => 'bg-green-100',
            self::Expired => 'bg-red-100',
        };
    }
}
