<?php

namespace Database\Factories;

use App\Models\Book;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Review>
 */
class ReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $comments = [
            1 => [
                '残念ながら合いませんでした。',
                '期待と違いました。',
            ],
            2 => [
                '少し期待外れでした。',
                '内容が薄い印象。',
                'もう少し深掘りしてほしかった。',
            ],
            3 => [
                '可もなく不可もなく。',
                '普通でした。',
                '期待したほどではなかった。',
            ],
            4 => [
                'とても参考になりました。',
                '読みやすくておすすめです。',
                '期待通りの内容でした。',
            ],
            5 => [
                '素晴らしい本でした！',
                '人生が変わりました。',
                '何度も読み返しています。',
            ],
        ];

        $rating = fake()->numberBetween(1, 5);
        return [
            'book_id' => Book::find(1)?->id ?? Book::factory()->create()->id,
            'user_id' => User::find(1)?->id ?? User::factory()->create()->id,
            'rating' => $rating,
            'comment' => fake()->randomElement($comments[$rating])
        ];
    }
}
