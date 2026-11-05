<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReviewSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void {
        Book::all()->each(function ($book) {
            // 全書籍に対してレビュー数を2~4でランダムに割り当てる
            $reviewCount = rand(2, 4);

            // レビュー投稿者もランダムで決める
            User::all()->random($reviewCount)
                ->each(function ($user) use ($book) {
                    Review::factory()->create([
                        'book_id' => $book->id,
                        'user_id' => $user->id,
                    ]);
                });
        });
    }
}
