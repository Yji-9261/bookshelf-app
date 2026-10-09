<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Review;
use App\Models\User;
use App\Models\Book;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1書籍に対して同一ユーザーが重複しない様に調整
        $combinations = [
            ['user_id' => 1, 'book_id' => 1],
            ['user_id' => 1, 'book_id' => 2],
            ['user_id' => 1, 'book_id' => 3],
            ['user_id' => 1, 'book_id' => 4],
            ['user_id' => 1, 'book_id' => 5],
            ['user_id' => 1, 'book_id' => 6],
            ['user_id' => 1, 'book_id' => 7],
            ['user_id' => 1, 'book_id' => 8],

            ['user_id' => 2, 'book_id' => 7],
            ['user_id' => 2, 'book_id' => 8],
            ['user_id' => 2, 'book_id' => 9],
            ['user_id' => 2, 'book_id' => 10],
            ['user_id' => 2, 'book_id' => 11],
            ['user_id' => 2, 'book_id' => 1],

            ['user_id' => 3, 'book_id' => 2],
            ['user_id' => 3, 'book_id' => 3],
            ['user_id' => 3, 'book_id' => 4],
            ['user_id' => 3, 'book_id' => 5],
            ['user_id' => 3, 'book_id' => 6],
            ['user_id' => 3, 'book_id' => 7],

            ['user_id' => 4, 'book_id' => 8],
            ['user_id' => 4, 'book_id' => 9],
            ['user_id' => 4, 'book_id' => 10],
            ['user_id' => 4, 'book_id' => 11],
            ['user_id' => 4, 'book_id' => 1],
            ['user_id' => 4, 'book_id' => 2],

            ['user_id' => 5, 'book_id' => 6],
            ['user_id' => 5, 'book_id' => 7],
            ['user_id' => 5, 'book_id' => 8],
            ['user_id' => 5, 'book_id' => 9],
            ['user_id' => 5, 'book_id' => 10],
            ['user_id' => 5, 'book_id' => 11],
        ];

        foreach ($combinations as $combination) {
            Review::factory()->create([
                'user_id' => $combination['user_id'],
                'book_id' => $combination['book_id'],
            ]);
        }
    }
}
