<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e) {
        // API実行時、対象となるリソースIDが見つからなかった場合のエラーレスポンス定義
        if ($e instanceof ModelNotFoundException) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => '書籍情報が見つかりませんでした。',
                ], 404);
            }
        }

        // API実行時、非認可アクション時のエラーレスポンス定義
        if ($e instanceof AuthorizationException) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => 'この操作を実行する権限がありません。',
                ], 403);
            }
        }

        return parent::render($request, $e);
    }
}
