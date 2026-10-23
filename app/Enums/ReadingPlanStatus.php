<?php
namespace App\Enums;

enum ReadingPlanStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label()
    {
        return match ($this) {
            self::InProgress => '読書中',
            self::Completed => '読了',
        };
    }

    public function badgeClass()
    {
        return match ($this) {
            self::InProgress => 'bg-blue-100',
            self::Completed => 'bg-green-100',
        };
    }
}