<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AcademicHistory;
use App\Models\User;

class AcademicHistoryPolicy
{
    public function import(User $user): bool
    {
        return $user->canAccessApi() && $user->rol === UserRole::COORDINADOR;
    }

    public function view(User $user, AcademicHistory $history): bool
    {
        return $user->canAccessApi() && ($user->rol === UserRole::COORDINADOR || $user->student?->id === $history->student_id);
    }

    public function update(User $user, AcademicHistory $history): bool
    {
        return false;
    }
}
