<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\PreEnrollmentRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PreEnrollmentRequestPolicy
{
    public function view(User $user, PreEnrollmentRequest $request): Response
    {
        return $user->canAccessApi() && ($user->rol === UserRole::COORDINADOR || $user->student?->id === $request->student_id)
            ? Response::allow() : Response::denyAsNotFound();
    }
}
