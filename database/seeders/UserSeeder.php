<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['name' => '山田太郎', 'email' => 'yamada@example.com', 'password' => 'password'],
            ['name' => '鈴木花子', 'email' => 'suzuki@example.com', 'password' => 'password'],
            ['name' => '田中一郎', 'email' => 'tanaka@example.com', 'password' => 'password'],
            ['name' => '佐藤美咲', 'email' => 'sato@example.com', 'password' => 'password'],
            ['name' => '高橋健太', 'email' => 'takahashi@example.com', 'password' => 'password'],
        ];

        foreach ($users as $user) {
            User::firstOrCreate([
                'name' => $user['name'],
                'email' => $user['email'],
                'password' => $user['password'],
            ]);
        }
    }
}
