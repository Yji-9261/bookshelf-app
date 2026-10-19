<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public const NAME = 'user1';

    public const EMAIL = 'user1@example.com';

    public const PASSWORD = 'password';

    /**
     * 名前入力のバリデーションエラーメッセージが表示される
     */
    public function testUserName(): void
    {
        // 1. 名前以外のユーザー情報を入力して登録
        // 2. テスト: 「お名前を入力してください」というバリデーションメッセージが表示される
        // 3. 他の項目は有効な値とし、名前に256文字を入力して登録
        // 4. テスト: 「お名前を255文字以内で入力してください」というバリデーションメッセージが表示される

        // 1,2(認証失敗していることも確認)
        $this->registerUser(name: '')
            ->assertSessionHasErrors([
                'name' => 'お名前を入力してください',
            ]);
        $this->assertGuest();

        // 3,4(認証失敗していることも確認)
        $this->registerUser(name: str_repeat('あ', 256))
            ->assertSessionHasErrors([
                'name' => 'お名前を255文字以内で入力してください',
            ]);
        $this->assertGuest();
    }

    /**
     * メールアドレスのバリデーションエラーメッセージが表示される
     */
    public function testUserEmail(): void
    {
        // 1. メールアドレス以外のユーザー情報を入力し登録する
        // 2. テスト: 「メールアドレスを入力してください」というバリデーションメッセージが表示される
        // 3. メールアドレス以外のユーザー情報を入力し、メールアドレスにアドレス形式でない文字列を入力して登録する
        // 4. テスト: 「メールアドレスをメール形式で入力してください」というバリデーションメッセージが表示される
        // 5. メールアドレス以外のユーザー情報を入力し、メールアドレスにすでに登録済みのメールアドレスを入力して登録する
        // 6. テスト: 「このメールアドレスはすでに登録されています」というバリデーションメッセージが表示される

        // 1,2(認証失敗していることも確認)
        $this->registerUser(email: '')
            ->assertSessionHasErrors([
                'email' => 'メールアドレスを入力してください',
            ]);
        $this->assertGuest();

        // 3,4(認証失敗していることも確認)
        $this->registerUser(email: 'invalid-email')
            ->assertSessionHasErrors([
                'email' => 'メールアドレスをメール形式で入力してください',
            ]);
        $this->assertGuest();

        //5,6(認証失敗していることも確認)
        User::factory()->create(['email' => self::EMAIL]);
        $this->registerUser(email: self::EMAIL)
            ->assertSessionHasErrors([
                'email' => 'このメールアドレスはすでに登録されています',
            ]);
        $this->assertGuest();
    }

    /**
     * パスワードが8文字未満の場合、バリデーションメッセージが表示される
     */
    public function testUserPasswordMin(): void
    {
        // 1. パスワードを8文字未満にし、ユーザー情報を入力する
        // 2. 会員登録の処理を行う
        // 「パスワードは8文字以上で入力してください」というバリデーションメッセージが表示される

        $this->registerUser(password: 'pass')
            ->assertSessionHasErrors([
                'password' => 'パスワードは8文字以上で入力してください',
            ]);

        // 認証失敗することを確認
        $this->assertGuest();
    }

    /**
     * パスワードが一致しない場合、バリデーションメッセージが表示される
     */
    public function testUserPasswordConfirmed(): void
    {
        // 1. 確認用のパスワードとパスワードを一致させず、ユーザー情報を入力する
        // 2. 会員登録の処理を行う
        // 「パスワードと一致しません」というバリデーションメッセージが表示される

        $this->registerUser(
            password: 'password',
            password_confirmation: 'passward'
        )
            ->assertSessionHasErrors([
                'password' => 'パスワードと一致しません',
            ]);

        // 認証失敗することを確認
        $this->assertGuest();
    }

    /**
     * パスワードが未入力の場合、バリデーションメッセージが表示される
     */
    public function testUserPasswordRequired(): void
    {
        // 1. パスワード以外のユーザー情報を入力する
        // 2. 会員登録の処理を行う
        // 「パスワードを入力してください」というバリデーションメッセージが表示される

        $this->registerUser(
            password: '',
        )
            ->assertSessionHasErrors([
                'password' => 'パスワードを入力してください',
            ]);

        // 認証失敗することを確認
        $this->assertGuest();
    }

    /**
     * フォームに内容が入力されていた場合、データが正常に保存される
     */
    public function testUserCanRegister(): void
    {
        // 1. 正常の入力で会員登録の処理を行う
        // 意図した遷移先('/')にリダイレクトされるか？
        // データベースに登録したユーザー情報が保存される

        $this->registerUser()->assertRedirect('/');
        $this->assertDatabaseHas('users', [
            'name' => self::NAME,
            'email' => self::EMAIL,
        ]);

        // 認証チェック
        $this->assertAuthenticated();
    }

    /**
     * 会員登録画面が正しく表示され、ログイン画面に遷移できるか？
     */
    public function testUserCanTransitionLogin(): void
    {
        /**
         * 1.登録画面が正しく表示され、Login画面へのリンクがあるか？
         * 2.getでリンク先にアクセス
         * 3.auth.loginのviewが表示されているか？
         */

        // 1
        $loginUrl = route('login');
        $this->get(route('register'))
            ->assertOk()
            ->assertViewIs('auth.register')
            ->assertSee('href="' . $loginUrl . '"', false);

        // 2
        $response = $this->get($loginUrl);

        // 3
        $response->assertOk()->assertViewIs('auth.login');
    }

    /**
     * 新規登録処理
     *
     * @param  mixed  $name
     * @param  mixed  $email
     * @param  mixed  $password
     * @param  mixed  $password_confirmation
     * @return TestResponse
     */
    private function registerUser(
        $name = self::NAME,
        $email = self::EMAIL,
        $password = self::PASSWORD,
        $password_confirmation = null,
    ): TestResponse {
        return $this->post('/register', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password_confirmation ?? $password,
        ]);
    }
}
