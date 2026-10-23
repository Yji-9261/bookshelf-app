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
    public function run(): void
    {
        Book::all()->each(function ($book) {
            $reviewCount = rand(2, 4);
            User::all()->random($reviewCount)->each(function ($user) use ($book) {
                Review::factory()->create([
                    'book_id' => $book->id,
                    'user_id' => $user->id,
                ]);
            });
        });
    }
}
