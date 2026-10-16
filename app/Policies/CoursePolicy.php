<?php

namespace App\Policies;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CoursePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->canAccessApi();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Course $course): Response
    {
        return $this->viewAny($user) && ($this->manageCurriculum($user) || $course->estado === RecordStatus::ACTIVO)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Course $course): bool
    {
        return $this->manageCurriculum($user);
    }

    public function manageCurriculum(User $user): bool
    {
        return $user->estado === RecordStatus::ACTIVO && $user->rol === UserRole::COORDINADOR;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Course $course): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Course $course): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Course $course): bool
    {
        return false;
    }
}
