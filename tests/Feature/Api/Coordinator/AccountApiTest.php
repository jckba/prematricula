<?php

namespace Tests\Feature\Api\Coordinator;

use App\Enums\CoordinatorPosition;
use App\Enums\RecordStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_both_positions_can_create_student_accounts_with_hashed_passwords(CoordinatorPosition $position): void
    {
        $coordinator = User::factory()->coordinator($position)->create();

        $response = $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->postJson('/api/v1/coordinator/accounts', $this->accountPayload());

        $response->assertCreated()->assertJsonPath('data.rol', 'ESTUDIANTE')
            ->assertJsonPath('data.estado', 'ACTIVO')->assertJsonPath('data.cargo', null)
            ->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.token');
        $account = User::findOrFail($response->json('data.id'));
        $this->assertTrue(Hash::check('demo-password', $account->password));
        $this->assertDatabaseHas('students', ['user_id' => $account->id, 'codigo_universitario' => '0014527007']);
    }

    public function test_coordinator_list_contains_only_student_accounts_and_no_secrets(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $student = Student::factory()->create();
        User::factory()->coordinator(CoordinatorPosition::SECRETARIA)->create();

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->getJson('/api/v1/coordinator/accounts')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $student->user_id)
            ->assertJsonMissingPath('data.0.password')
            ->assertJsonMissingPath('data.0.remember_token')
            ->assertJsonMissingPath('data.0.token');
    }

    public function test_coordinator_can_consult_a_student_account(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $student = Student::factory()->create();

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->getJson('/api/v1/coordinator/accounts/'.$student->user_id)
            ->assertOk()->assertJsonPath('data.id', $student->user_id)
            ->assertJsonMissingPath('data.password');
    }

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_both_positions_can_deactivate_accounts_and_revoke_all_their_tokens(CoordinatorPosition $position): void
    {
        $coordinator = User::factory()->coordinator($position)->create();
        $student = Student::factory()->create();
        $studentToken = $student->user->createToken('student');
        $student->user->createToken('another-device');
        $coordinatorToken = $coordinator->createToken('test');

        $this->withToken($coordinatorToken->plainTextToken)
            ->patchJson('/api/v1/coordinator/accounts/'.$student->user_id, ['estado' => 'INACTIVO'])
            ->assertOk()->assertJsonPath('data.estado', 'INACTIVO');

        $this->assertSame(RecordStatus::INACTIVO, $student->user->refresh()->estado);
        $this->assertSame(0, $student->user->tokens()->count());
        $this->assertModelExists($coordinatorToken->accessToken);

        $this->app['auth']->forgetGuards();
        $this->withToken($studentToken->plainTextToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_coordinator_can_reactivate_a_student_account(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $student = Student::factory()->for(User::factory()->inactive())->create();

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/accounts/'.$student->user_id, ['estado' => 'ACTIVO'])
            ->assertOk()->assertJsonPath('data.estado', 'ACTIVO');

        $this->assertSame(RecordStatus::ACTIVO, $student->user->refresh()->estado);
        $this->assertSame(0, $student->user->tokens()->count());
    }

    public function test_password_reset_revokes_all_student_tokens_and_hashes_new_password(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $student = Student::factory()->create();
        $student->user->createToken('test');

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/accounts/'.$student->user_id, ['password' => 'changed-password'])
            ->assertOk()->assertJsonMissingPath('data.password');

        $this->assertTrue(Hash::check('changed-password', $student->user->refresh()->password));
        $this->assertSame(0, $student->user->tokens()->count());
    }

    public function test_email_change_revokes_all_student_tokens(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $student = Student::factory()->create();
        $student->user->createToken('test');

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/accounts/'.$student->user_id, ['correo_institucional' => 'updated@unitru.edu.pe'])
            ->assertOk()->assertJsonPath('data.correo_institucional', 'updated@unitru.edu.pe');

        $this->assertDatabaseHas('users', ['id' => $student->user_id, 'correo_institucional' => 'updated@unitru.edu.pe']);
        $this->assertSame(0, $student->user->tokens()->count());
    }

    #[TestWith(['GET'])]
    #[TestWith(['POST'])]
    public function test_returns_401_for_account_endpoints_without_authentication(string $method): void
    {
        $this->json($method, '/api/v1/coordinator/accounts', $this->accountPayload())->assertUnauthorized();
        $this->assertDatabaseCount('users', 0);
    }

    #[TestWith(['GET'])]
    #[TestWith(['POST'])]
    public function test_returns_403_for_students_attempting_to_manage_accounts(string $method): void
    {
        $student = Student::factory()->create();

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->json($method, '/api/v1/coordinator/accounts', $this->accountPayload())->assertForbidden();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_returns_403_for_students_attempting_to_update_another_account(): void
    {
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();

        $this->withToken($student->user->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/accounts/'.$otherStudent->user_id, ['estado' => 'INACTIVO'])
            ->assertForbidden();

        $this->assertSame(RecordStatus::ACTIVO, $otherStudent->user->refresh()->estado);
    }

    public function test_returns_403_for_coordinators_attempting_to_change_a_coordinator_account(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $otherCoordinator = User::factory()->coordinator(CoordinatorPosition::SECRETARIA)->create();

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/accounts/'.$otherCoordinator->id, ['estado' => 'INACTIVO'])
            ->assertForbidden();

        $this->assertSame(RecordStatus::ACTIVO, $otherCoordinator->refresh()->estado);
    }

    public function test_returns_422_for_role_or_position_escalation(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $student = Student::factory()->create();

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/accounts/'.$student->user_id, [
                'rol' => 'COORDINADOR', 'cargo' => 'DIRECTOR_ESCUELA',
            ])->assertUnprocessable()->assertJsonValidationErrors(['rol', 'cargo']);

        $this->assertSame('ESTUDIANTE', $student->user->refresh()->rol->value);
    }

    #[TestWith(['rol', 'COORDINADOR'])]
    #[TestWith(['rol', 'DOCENTE'])]
    #[TestWith(['cargo', 'DIRECTOR_ESCUELA'])]
    #[TestWith(['cargo', 'SECRETARIA'])]
    public function test_returns_422_when_creation_attempts_to_assign_roles_or_positions(string $field, string $value): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $payload = $this->accountPayload() + [$field => $value];

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->postJson('/api/v1/coordinator/accounts', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_returns_422_without_creating_accounts_for_invalid_student_profiles(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $payload = $this->accountPayload();
        $payload['estudiante']['codigo_universitario'] = 14527007;

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->postJson('/api/v1/coordinator/accounts', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors('estudiante.codigo_universitario');

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('students', 0);
    }

    public function test_returns_422_for_duplicate_email_or_university_code(): void
    {
        $coordinator = User::factory()->coordinator()->create();
        $student = Student::factory()->create(['codigo_universitario' => '0014527007']);
        $payload = $this->accountPayload();
        $payload['correo_institucional'] = $student->user->correo_institucional;

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->postJson('/api/v1/coordinator/accounts', $payload)->assertUnprocessable()
            ->assertJsonValidationErrors(['correo_institucional', 'estudiante.codigo_universitario']);

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_public_registration_is_not_available(): void
    {
        $this->postJson('/api/v1/register', $this->accountPayload())->assertNotFound();

        $this->assertDatabaseCount('users', 0);
    }

    #[TestWith(['aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'])]
    #[TestWith(['ééééééééééééééééééééééééééééééééééééé'])]
    #[TestWith(["password\0suffix"])]
    public function test_returns_422_for_passwords_that_bcrypt_cannot_preserve(string $password): void
    {
        config(['hashing.driver' => 'bcrypt']);
        $coordinator = User::factory()->coordinator()->create();
        $payload = $this->accountPayload();
        $payload['password'] = $password;

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->postJson('/api/v1/coordinator/accounts', $payload)->assertUnprocessable()
            ->assertJsonPath('errors.password.0', 'La contraseña admite hasta 72 bytes y no puede contener bytes nulos.');

        $this->assertDatabaseCount('users', 1);
    }

    public function test_returns_422_without_resetting_password_or_revoking_tokens_for_invalid_password(): void
    {
        config(['hashing.driver' => 'bcrypt']);
        $coordinator = User::factory()->coordinator()->create();
        $student = Student::factory()->create();
        $token = $student->user->createToken('test');

        $this->withToken($coordinator->createToken('test')->plainTextToken)
            ->patchJson('/api/v1/coordinator/accounts/'.$student->user_id, ['password' => str_repeat('a', 73)])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->assertTrue(Hash::check('password', $student->user->refresh()->password));
        $this->assertModelExists($token->accessToken);
    }

    /** @return array{correo_institucional: string, password: string, estudiante: array{codigo_universitario: string, nombres: string, apellidos: string}} */
    private function accountPayload(): array
    {
        return [
            'correo_institucional' => 'new-student@unitru.edu.pe',
            'password' => 'demo-password',
            'estudiante' => [
                'codigo_universitario' => '0014527007',
                'nombres' => 'Alumno',
                'apellidos' => 'Prueba',
            ],
        ];
    }
}
