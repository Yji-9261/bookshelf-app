<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Book;
use App\Models\Genre;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;


class AuthorizationTest extends TestCase
{
    /**
     * 認証ユーザーが登録していない書籍の編集画面にアクセスする
     */
    public function test_example(): void
    {
        // 1. GET /books/{book}/edit パスパラメータに認証ユーザー以外が登録した書籍を指定する
        // 2. テスト: 403 Forbiddenが表示される
        $createdUser = User::factory()->create();
        $loginUser = User::factory()->create();
        $book = $createdUser->registeredBooks()->create();
        $book->genres()->sync(Genre::createOrFirst(['name' => '小説'])->id);


        $response = $this->actingAs($loginUser)
            ->get("/books/{$book->id}/edit");

        $response->assertStatus(403)->assertSee('Forbidden');
    }
}
