<?php

namespace App\Policies;

use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ReadingPlan $readingPlan): bool {
        return $user->id === $readingPlan->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool {
        return $user->id === $readingPlan->user_id;
    }
}
