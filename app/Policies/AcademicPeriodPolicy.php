<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\AcademicPeriod;
use App\Models\User;

class AcademicPeriodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAccessApi();
    }

    public function view(User $user, AcademicPeriod $period): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->canAccessApi() && $user->rol === UserRole::COORDINADOR;
    }

    public function update(User $user, AcademicPeriod $period): bool
    {
        return $this->create($user);
    }

    public function delete(User $user, AcademicPeriod $period): bool
    {
        return $this->create($user);
    }
}
