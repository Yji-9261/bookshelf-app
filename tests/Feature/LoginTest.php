<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ユーザーemail
     *
     * @var string
     */
    private const USER_EMAIL = 'user1@example.com';

    /**
     * ユーザーpassword
     *
     * @var string
     */
    private const USER_PASSWORD = 'password';

    /**
     * 存在しないemail
     *
     * @var string
     */
    private const ANONYMOUS_EMAIL = 'guest@example.com';

    /**
     * 存在しないpassword
     *
     * @var string
     */
    private const ANONYMOUS_PASSWORD = 'pass';

    protected function setUp(): void
    {
        parent::setUp();

        // ユーザー登録
        User::factory()->create([
            'name' => 'user1',
            'email' => self::USER_EMAIL,
            'password' => self::USER_PASSWORD,
        ]);
    }

    /**
     * メールアドレスが未入力の場合、バリデーションメッセージが表示される
     */
    public function testUserEmailRequiredValidation(): void
    {
        // 1.       ユーザーを登録する
        // 2.       メールアドレス以外のユーザー情報を入力する
        // 3.       ログインの処理を行う
        // result.  「メールアドレスを入力してください」というバリデーションメッセージが表示される

        $this->loginUser(email: '')
            ->assertSessionHasErrors([
                'email' => 'メールアドレスを入力してください',
            ]);

        // 認証されていないか確認
        $this->assertGuest();
    }

    /**
     * パスワードが未入力の場合、バリデーションメッセージが表示される
     */
    public function testUserPasswordRequiredValidation(): void
    {
        // 1.       ユーザーを登録する
        // 2.       パスワード以外のユーザー情報を入力する
        // 3.       ログインの処理を行う
        // result.  「パスワードを入力してください」というバリデーションメッセージが表示される

        $this->loginUser(password: '')
            ->assertSessionHasErrors([
                'password' => 'パスワードを入力してください',
            ]);

        // 認証されていないか確認
        $this->assertGuest();
    }

    /**
     *  登録内容と一致しない場合、バリデーションメッセージが表示される
     */
    public function testUserEmailNotRegisterValidation(): void
    {
        // 1. ユーザーを登録する
        // 2. 誤ったユーザー情報を入力する
        // 3. ログインの処理を行う
        // 「ログイン情報が登録されていません」というバリデーションメッセージが表示される

        // email間違い
        $this->loginUser(email: self::ANONYMOUS_EMAIL)
            ->assertSessionHasErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);

        // password間違い
        $this->loginUser(password: self::ANONYMOUS_PASSWORD)
            ->assertSessionHasErrors([
                'email' => 'ログイン情報が登録されていません',
            ]);

        // 認証されていないか確認
        $this->assertGuest();
    }

    /**
     * ログアウト後の挙動が意図したものか
     */
    public function testLogoutBehavior(): void
    {
        /**
         * 1. POSTでログインし、認証されていることを確認
         * 2. route('logout')にPOSTし、ログアウト
         * 3. テスト: route(login)にリダイレクトされているか
         * 4. テスト: 認証が解除されているか
         */

        // 1
        $this->loginUser()->assertSessionHasNoErrors();
        $this->assertAuthenticated();

        // 2,3
        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        // 4
        $this->assertGuest();
    }


    /**
     * ユーザーログイン
     *
     * @param  mixed  $email
     * @param  mixed  $password
     * @return TestResponse
     */
    private function loginUser(
        $email = self::USER_EMAIL,
        $password = self::USER_PASSWORD
    ) {
        return $this->post('/login', [
            'email' => $email,
            'password' => $password,
        ]);
    }
}
