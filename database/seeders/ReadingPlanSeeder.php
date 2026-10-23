<?php

namespace Database\Seeders;

use App\Models\ReadingPlan;
use App\Models\User;
use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ReadingPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $yamada = User::where('name', '山田太郎')->firstOrFail();
        $suzuki = User::where('name', '鈴木花子')->firstOrFail();

        $initialValues = [
            // 山田太郎（主要シナリオ集約、ID 1〜5）:
            // 1. target_date = Carbon::today()->addDays(3) / status = 'in_progress' → 3日前リマインダー対象
            [
                'book_id' => 1,
                'user_id' => $yamada->id,
                'target_date' => today()->addDays(3),
                'status' => ReadingPlanStatus::InProgress->value,
                'completed_at' => null,
            ],

            // 2. target_date = Carbon::today() / status = 'in_progress' → 当日リマインダー対象
            [
                'book_id' => 2,
                'user_id' => $yamada->id,
                'target_date' => today(),
                'status' => ReadingPlanStatus::InProgress->value,
                'completed_at' => null,
            ],

            // 3. target_date = Carbon::today()->subDays(3) / status = 'in_progress' → バッチで Auto-expire 化 + 3日後再エンゲージメント対象（二重シナリオ）
            [
                'book_id' => 3,
                'user_id' => $yamada->id,
                'target_date' => today()->subDays(3),
                'status' => ReadingPlanStatus::InProgress->value,
                'completed_at' => null,
            ],

            // 4. target_date = Carbon::today()->addDays(7) / status = 'in_progress' → リマインダー対象外
            [
                'book_id' => 4,
                'user_id' => $yamada->id,
                'target_date' => today()->addDays(7),
                'status' => ReadingPlanStatus::InProgress->value,
                'completed_at' => null,
            ],

            // 5. target_date = Carbon::today()->subDays(10) / status = 'completed' / completed_at = Carbon::today()->subDays(5) → 完了済み
            [
                'book_id' => 5,
                'user_id' => $yamada->id,
                'target_date' => today()->subDays(10),
                'status' => ReadingPlanStatus::Completed->value,
                'completed_at' => today()->subDays(5),
            ],

            // 鈴木花子（他ユーザー認可テスト用、ID 6）:
            // 6. target_date = Carbon::today()->addDays(5) / status = 'in_progress' → 山田太郎ログイン中に URL /reading-plans/6/edit 直打ちで 403 確認
            [
                'book_id' => 1,
                'user_id' => $suzuki->id,
                'target_date' => today()->addDays(5),
                'status' => ReadingPlanStatus::InProgress->value,
                'completed_at' => null,
            ],
        ];

        foreach ($initialValues as $value) {
            ReadingPlan::create($value);
        }
    }
}
