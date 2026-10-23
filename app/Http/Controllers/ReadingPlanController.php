<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Enums\ReadingPlanStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Requests\IndexReadingPlanRequest;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use Illuminate\Database\Eloquent\Collection;

class ReadingPlanController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(IndexReadingPlanRequest $request)
    {
        $validated = $request->validated();

        $plans = $request->user()->readingPlans()->when(
            $validated['status'] ?? null,
            function ($query, $status) {
                return $query->where('status', $status);
            }
        )->get();

        return View('reading-plans.index', [
            'currentStatus' => $validated['status'] ?? null,
            'readingPlans' => $plans,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return View('reading-plans.create', [
            'books' => Book::all(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreReadingPlanRequest $request)
    {
        $validated = $request->validated();

        // DB登録
        $request->user()->readingPlans()->create([
            'book_id' => $validated['book_id'],
            'target_date' => $validated['target_date'],
        ]);

        return redirect(route('reading-plans.index'))->with('success', '読書計画を作成しました。');
    }

    // /**
    //  * Display the specified resource.
    //  */
    // public function show(ReadingPlan $plan)
    // {
    //     //

    // }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ReadingPlan $plan)
    {
        $this->authorize('update', $plan);

        return View('reading-plans.edit', [
            'readingPlan' => $plan
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $plan)
    {
        $this->authorize('update', $plan);

        $validated = $request->validated();

        $plan->update($validated);
        return redirect(route('reading-plans.index'))->with('success', '読書計画を更新しました。');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ReadingPlan $plan)
    {
        $this->authorize('delete', $plan);

        $plan->delete();
        return redirect(route('reading-plans.index'))->with('success', '読書計画を削除しました。');
    }


    public function complete(ReadingPlan $plan)
    {
        $this->authorize('update', $plan);

        $plan->update([
            'status' => ReadingPlanStatus::Completed->value,
            'completed_at' => Carbon::now()->format('Y-m-d'),
        ]);
        return redirect(route('reading-plans.index'))->with('success', '読書計画を完了しました。');
    }
}
