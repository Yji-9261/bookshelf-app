<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Book;

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
    public function run(): void
    {
        $users = User::all();
        foreach ($this->initialValues as $index => $values) {
            $users[$index]->favoriteBooks()->syncWithoutDetaching($values);
        }
    }
}
