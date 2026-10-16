<?php

namespace Tests\Feature\Policies;

use App\Enums\CoordinatorPosition;
use App\Enums\RecordStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    /** @return array<string, array{UserRole, ?CoordinatorPosition, RecordStatus, bool}> */
    public static function actors(): array
    {
        return [
            'director' => [UserRole::COORDINADOR, CoordinatorPosition::DIRECTOR_ESCUELA, RecordStatus::ACTIVO, true],
            'secretary' => [UserRole::COORDINADOR, CoordinatorPosition::SECRETARIA, RecordStatus::ACTIVO, true],
            'legacy coordinator' => [UserRole::COORDINADOR, null, RecordStatus::ACTIVO, true],
            'inactive director' => [UserRole::COORDINADOR, CoordinatorPosition::DIRECTOR_ESCUELA, RecordStatus::INACTIVO, false],
            'inactive secretary' => [UserRole::COORDINADOR, CoordinatorPosition::SECRETARIA, RecordStatus::INACTIVO, false],
            'student' => [UserRole::ESTUDIANTE, null, RecordStatus::ACTIVO, false],
        ];
    }

    #[DataProvider('actors')]
    public function test_account_policy_preserves_equal_permissions_and_restricts_target_accounts(
        UserRole $role,
        ?CoordinatorPosition $position,
        RecordStatus $status,
        bool $allowed
    ): void {
        $actor = User::factory()->make(['rol' => $role, 'cargo' => $position, 'estado' => $status]);
        $student = User::factory()->make();
        $coordinator = User::factory()->coordinator()->make();
        $gate = Gate::forUser($actor);

        $this->assertSame($allowed, $gate->allows('viewAny', User::class));
        $this->assertSame($allowed, $gate->allows('create', User::class));
        $this->assertSame($allowed, $gate->allows('view', $student));
        $this->assertSame($allowed, $gate->allows('update', $student));
        $this->assertFalse($gate->allows('view', $coordinator));
        $this->assertFalse($gate->allows('update', $coordinator));
        $this->assertFalse($gate->allows('delete', $student));
    }
}
