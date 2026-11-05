<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 正常なリクエストでログインできる
     */
    public function testCanLogin(): void {
        // 1. ユーザー作成
        // 2. 作成したユーザーのemailとpasswordをPOSTデータとして、POST /api/v1/loginにアクセス
        // 3. 期待通りのレスポンスが返る
        // 4. tokenが格納される

        // 1
        $user = User::factory()->create(['password' => 'password']);

        // トークンがまだ格納されていないことを検証する
        $this->assertSame(0, $user->tokens()->count());

        // 2,3
        $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJson([
                'message' => '認証に成功しました',
            ]);

        // 4
        $this->assertSame(1, $user->tokens()->count());
    }

    /**
     * 不正なリクエストで入力を行った場合、認証に失敗し期待するエラーレスポンスが返る
     */
    public function testLoginInvalid(): void {
        // 1. 'email':'' 'password':''をPOSTデータとして、POST /api/v1/loginにアクセス
        // 2. テスト: 期待通りのエラーレスポンスが返る
        // 3. 'email':'****' 'password':['****']をPOSTデータとして、POST /api/v1/loginにアクセス
        // 4. テスト: 期待通りのエラーレスポンスが返る
        // 5. 'email': 'test@example.com' 'password': 'password'をPOSTデータとして、POST /api/v1/loginにアクセス
        // 6. テスト: 期待通りのエラーレスポンスが返る

        // 1,2
        $this->postJson('/api/v1/login', ['email' => '', 'password' => ''])
            ->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'email' => [
                        'emailを入力してください',
                    ],
                    'password' => [
                        'パスワードを入力してください',
                    ],
                ],
            ]);

        // 3,4
        $this->postJson('/api/v1/login', ['email' => '****', 'password' => ['****']])
            ->assertStatus(422)
            ->assertJson([
                'errors' => [
                    'email' => [
                        'emailはメール形式で入力してください',
                    ],
                    'password' => [
                        'パスワードは文字列を入力してください',
                    ],
                ],
            ]);

        // 5,6
        $this->postJson('/api/v1/login', ['email' => 'test@example.com', 'password' => 'password'])
            ->assertStatus(401)
            ->assertJson([
                'error' => 'ログイン情報が登録されていません',
            ]);
    }

    /**
     * 認証ユーザーはログアウトできる
     */
    public function testUserCanLogout(): void {
        // 1. 認証ユーザーががPOST /api/v1/logoutアクセスする
        // 2. テスト: ログアウト成功し、期待通りのレスポンスが返る

        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->postJson('/api/v1/logout')
            ->assertStatus(200)
            ->assertJson([
                'message' => 'ログアウトしました',
            ]);
    }

    /**
     * ゲストはログアウトできない
     */
    public function testGuestCannotLogout(): void {
        // 1. ゲストがPOST /api/v1/logoutアクセスする
        // 2. テスト: ステータスコード401が返り、仕様通りのエラーレスポンスが返る

        $this->postJson('/api/v1/logout')
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }
}
