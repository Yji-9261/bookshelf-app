<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Requests\Api\V1\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;

class AuthenticationController extends Controller
{
    /**
     * トークン認証方式ログイン
     * @param Request $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // emailとパスワードを比較して一致しているならトークンを返す
        $user = User::where('email', $validated['email'])->first();
        if ($user && Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => '認証に成功しました',
                'token' => $user->createToken('api-token')->plainTextToken,
            ], 200);
        }

        return response()->json([
            'error' => 'ログイン情報が登録されていません',
        ], 401);
    }


    /**
     * トークン認証方式ログアウト
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
