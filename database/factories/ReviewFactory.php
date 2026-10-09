<?php

namespace Database\Factories;

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
        $rating = fake()->numberBetween(3, 5);
        $comments = [
            3 => [
                '可もなく不可もなく。',
                '面白かったが、よく分からない点も多かった。',
                '少し難解で読むのが大変でしたが、勉強になる点も多かったです。',
                '惜しい。次回作に期待します。',
                '万人には進められないが、私は面白かったと思います。',
            ],
            4 => [
                '期待していなかったが、思った以上に面白かった。',
                '非常に参考になります。ただし中級者以上向け。',
                '続きが気になりました。次回作も期待。',
                '内容は満足ですが、誤字などがあり少しマイナス。',
                '面白かった。だがもう少しボリュームが欲しい。',
            ],
            5 => [
                '大変読み応えがあり、大満足です。',
                '世紀に残る傑作といえます。',
                '何度も読み返したいです。',
                '次回作が待ち遠しいです。',
                'とてもわかりやすく、誰にでもお勧めできる内容です。',
            ],
        ];

        return [
            'rating' => $rating,
            'comment' => fake()->randomElement($comments[$rating])
        ];
    }
}
