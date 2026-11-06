<?php

namespace App\Console\Commands;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use Illuminate\Console\Command;

class ExpireReadingPlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:expire-reading-plan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '期限4日経過した読書計画の状態を期限切れにするコマンド';

    /**
     * Execute the console command.
     */
    public function handle() {
        // 読書計画読書中のもので、期限が4日経過した読書計画のstatusを期限切れにする
        ReadingPlan::with('user', 'book')
            ->where('status', ReadingPlanStatus::InProgress)
            ->whereDate('target_date', '<=', today()->subDays(4))
            ->update([
                'status' => ReadingPlanStatus::Expired,
            ]);
    }
}
