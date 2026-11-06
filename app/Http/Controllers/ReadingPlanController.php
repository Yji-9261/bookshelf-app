<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\IndexReadingPlanRequest;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class ReadingPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexReadingPlanRequest $request): View {
        $validated = $request->validated();

        $plans = $request->user()->readingPlans()->when(
            $validated['status'] ?? null,
            function ($query, $status) {
                return $query->where('status', $status);
            }
        )
            ->with('book')
            ->get();

        return View('reading-plans.index', [
            'currentStatus' => $validated['status'] ?? null,
            'readingPlans' => $plans,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View {
        return View('reading-plans.create', [
            'books' => Book::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse {
        $validated = $request->validated();

        $request->user()->readingPlans()->create([
            'book_id' => $validated['book_id'],
            'target_date' => $validated['target_date'],
        ]);

        return redirect(route('reading-plans.index'))->with('success', '読書計画を作成しました。');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReadingPlan $plan): View {
        $this->authorize('update', $plan);

        return View('reading-plans.edit', [
            'readingPlan' => $plan,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $plan): RedirectResponse {
        $this->authorize('update', $plan);

        $validated = $request->validated();

        // 期限切れの読書計画が期限更新により、現在日付よりも後になったら読書中に変更する
        if ($plan->status === ReadingPlanStatus::Expired) {
            if (now()->diffInDays(Carbon::parse($validated['target_date']), false) >= 0) {
                $validated['status'] = ReadingPlanStatus::InProgress;
            }
        }

        $plan->update($validated);

        return redirect(route('reading-plans.index'))->with('success', '読書計画を更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReadingPlan $plan): RedirectResponse {
        $this->authorize('delete', $plan);

        $plan->delete();

        return redirect(route('reading-plans.index'))->with('success', '読書計画を削除しました。');
    }

    /**
     * 読書計画状態を読了にする
     */
    public function complete(ReadingPlan $plan): RedirectResponse {
        $this->authorize('update', $plan);

        $plan->update([
            'status' => ReadingPlanStatus::Completed->value,
            'completed_at' => Carbon::now()->format('Y-m-d'),
        ]);

        return redirect(route('reading-plans.index'))->with('success', '読書計画を完了しました。');
    }
}
