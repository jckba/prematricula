<?php

namespace App\Http\Controllers\Api\Coordinator;

use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAccountRequest;
use App\Http\Requests\Api\UpdateAccountRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', User::class);

        return UserResource::collection(User::query()
            ->where('rol', UserRole::ESTUDIANTE)
            ->orderBy('id')
            ->paginate(20));
    }

    public function show(User $account): UserResource
    {
        Gate::authorize('view', $account);

        return new UserResource($account);
    }

    public function store(StoreAccountRequest $request): UserResource
    {
        $data = $request->validated();

        $account = DB::transaction(function () use ($data): User {
            $account = User::create([
                'correo_institucional' => $data['correo_institucional'],
                'password' => $data['password'],
                'rol' => UserRole::ESTUDIANTE,
                'estado' => RecordStatus::ACTIVO,
            ]);

            $account->student()->create($data['estudiante'] + ['estado' => RecordStatus::ACTIVO]);

            return $account;
        });

        return new UserResource($account);
    }

    public function update(UpdateAccountRequest $request, User $account): UserResource
    {
        $account = DB::transaction(function () use ($request, $account): User {
            $account = User::query()->lockForUpdate()->findOrFail($account->id);
            Gate::authorize('update', $account);
            $account->fill($request->validated());
            $mustRevokeTokens = $account->isDirty(['correo_institucional', 'password', 'estado']);
            $account->save();

            if ($mustRevokeTokens) {
                $account->tokens()->delete();
            }

            return $account;
        });

        return new UserResource($account);
    }
}
