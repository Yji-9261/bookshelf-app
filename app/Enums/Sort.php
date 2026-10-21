<?php
namespace App\Enums;

enum Sort: string
{
    case NEWEST = 'newest';
    case OLDEST = 'oldest';
    case RATING = 'rating';
    case TITLE = 'title';
}