<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;


class ReviewLikeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 登録ユーザーは５名存在する
        $userCount = User::count();

        foreach (Review::all() as $review) {
            // 1~5のユーザーIDから自分のレビューを除く
            $randmoUserIds = collect(range(1, $userCount))->reject($review->user_id);

            // 各レビューに0〜3人のユーザーがいいね
            $random = fake()->numberBetween(0, 3);
            $numbers = fake()->randomElements($randmoUserIds, $random);

            // いいねユーザーを紐つける
            $review->favoriteUsers()->syncWithoutDetaching($numbers);
        }
    }
}
