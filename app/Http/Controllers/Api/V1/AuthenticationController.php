<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\JsonResponse;

class AuthenticationController extends Controller
{
    /**
     * ログイン
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request): JsonResponse
    {
        // 未入力の時はnullが入るためemail(required),password(required)の認証で失敗する
        $credentials = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
        ];

        if (Auth::attempt($credentials)) {
            return response()->json([
                'message' => '認証に成功しました',
                'token' => $request->user()->createToken('api-token')->plainTextToken,
            ], 200);
        }

        return response()->json([
            'error' => 'ログイン情報が登録されていません',
        ], 401);
    }


    /**
     * ログアウト
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'ログアウトしました',
        ], 200);
    }
}
