<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class FavoriteSeeder extends Seeder
{
    public $initialValues = [
        // ユーザーID1、お気に入り書籍ID群
        [1, 2, 3],

        // ユーザーID2、お気に入り書籍ID群
        [4, 5, 6],

        // ユーザーID3、お気に入り書籍ID群
        [7, 8, 9],

        // ユーザーID4、お気に入り書籍ID群
        [1, 2, 3, 4, 5],

        // ユーザーID5、お気に入り書籍ID群
        [6, 7, 8, 9, 10],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void {
        foreach (User::all() as $index => $user) {
            $user->favoriteBooks()->syncWithoutDetaching($this->initialValues[$index]);
        }
    }
}
