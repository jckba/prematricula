<?php

namespace Tests\Feature\Api;

use App\Enums\CoordinatorPosition;
use App\Enums\RecordStatus;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class AuthenticationApiTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['identifier'])]
    #[TestWith(['correo_institucional'])]
    public function test_logs_in_with_institutional_email_and_preserves_legacy_contract(string $field): void
    {
        $student = Student::factory()->create();
        $this->travelTo(new \DateTimeImmutable('2026-10-16T10:00:00Z'));

        $response = $this->postJson('/api/v1/login', [
            $field => $student->user->correo_institucional,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Autenticación correcta.')
            ->assertJsonPath('data.user.id', $student->user_id)
            ->assertJsonPath('data.user.rol', 'ESTUDIANTE')
            ->assertJsonPath('data.user.cargo', null)
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');

        $token = PersonalAccessToken::findToken($response->json('data.token'));
        $this->assertSame($student->user_id, $token->tokenable_id);
        $this->assertSame('2026-10-16T18:00:00.000000Z', $response->json('data.expires_at'));
        $this->assertSame('2026-10-16T18:00:00.000000Z', $token->expires_at->toISOString());
    }

    public function test_logs_in_with_university_code_preserving_leading_zeroes(): void
    {
        $student = Student::factory()->create(['codigo_universitario' => '0014527007']);

        $response = $this->postJson('/api/v1/login', [
            'identifier' => '0014527007',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('data.user.id', $student->user_id);
        $this->assertSame('0014527007', $student->refresh()->codigo_universitario);
    }

    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_coordinators_log_in_without_student_profiles(CoordinatorPosition $position): void
    {
        $user = User::factory()->coordinator($position)->create();

        $this->postJson('/api/v1/login', [
            'identifier' => $user->correo_institucional,
            'password' => 'password',
        ])->assertOk()->assertJsonPath('data.user.cargo', $position->value);
    }

    public function test_returns_401_for_incorrect_password(): void
    {
        $student = Student::factory()->create();

        $this->postJson('/api/v1/login', [
            'identifier' => $student->user->correo_institucional,
            'password' => 'incorrect-password',
        ])->assertUnauthorized()->assertExactJson(['message' => 'Credenciales incorrectas.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[TestWith(['missing@unitru.edu.pe'])]
    #[TestWith(['0000000000'])]
    public function test_returns_401_for_unknown_identifiers(string $identifier): void
    {
        $this->postJson('/api/v1/login', [
            'identifier' => $identifier,
            'password' => 'password',
        ])->assertUnauthorized()->assertExactJson(['message' => 'Credenciales incorrectas.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_401_for_inactive_user(): void
    {
        $student = Student::factory()->for(User::factory()->inactive())->create();

        $this->postJson('/api/v1/login', [
            'identifier' => $student->codigo_universitario,
            'password' => 'password',
        ])->assertUnauthorized()->assertExactJson(['message' => 'Credenciales incorrectas.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_401_for_student_without_profile(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/v1/login', [
            'identifier' => $user->correo_institucional,
            'password' => 'password',
        ])->assertUnauthorized()->assertExactJson(['message' => 'Credenciales incorrectas.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_401_for_inactive_student_profile(): void
    {
        $student = Student::factory()->inactive()->create();

        $this->postJson('/api/v1/login', [
            'identifier' => $student->user->correo_institucional,
            'password' => 'password',
        ])->assertUnauthorized()->assertExactJson(['message' => 'Credenciales incorrectas.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_401_for_university_code_linked_to_a_coordinator_account(): void
    {
        $student = Student::factory()->for(User::factory()->coordinator())->create();

        $this->postJson('/api/v1/login', [
            'identifier' => $student->codigo_universitario, 'password' => 'password',
        ])->assertUnauthorized()->assertExactJson(['message' => 'Credenciales incorrectas.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_422_when_both_identifier_fields_are_sent(): void
    {
        $student = Student::factory()->create();

        $this->postJson('/api/v1/login', [
            'identifier' => $student->codigo_universitario,
            'correo_institucional' => $student->user->correo_institucional,
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors(['identifier', 'correo_institucional']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_401_for_email_and_student_code_collision(): void
    {
        $coordinator = User::factory()->coordinator()->create(['correo_institucional' => 'collision@unitru.edu.pe']);
        Student::factory()->create(['codigo_universitario' => $coordinator->correo_institucional]);

        $this->postJson('/api/v1/login', [
            'identifier' => $coordinator->correo_institucional,
            'password' => 'password',
        ])->assertUnauthorized()->assertExactJson(['message' => 'Credenciales incorrectas.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[TestWith([1452700722])]
    #[TestWith([['code' => '0014527007']])]
    #[TestWith([null])]
    public function test_returns_422_for_non_string_identifiers(mixed $identifier): void
    {
        $this->postJson('/api/v1/login', [
            'identifier' => $identifier,
            'password' => 'password',
        ])->assertUnprocessable()->assertJsonValidationErrors('identifier');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_422_for_missing_credentials(): void
    {
        $this->postJson('/api/v1/login', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['identifier', 'password']);
    }

    public function test_returns_current_user_using_a_real_bearer_token(): void
    {
        $user = User::factory()->coordinator(CoordinatorPosition::SECRETARIA)->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertExactJson([
            'data' => [
                'id' => $user->id,
                'correo_institucional' => $user->correo_institucional,
                'rol' => 'COORDINADOR',
                'estado' => 'ACTIVO',
                'cargo' => 'SECRETARIA',
            ],
        ]);
    }

    public function test_logout_revokes_only_the_current_token_and_it_cannot_be_reused(): void
    {
        $user = User::factory()->coordinator()->create();
        $token = $user->createToken('current');
        $otherToken = $user->createToken('other');

        $this->withToken($token->plainTextToken)->postJson('/api/v1/logout')
            ->assertOk()->assertExactJson(['message' => 'Sesión cerrada correctamente.']);

        $this->assertModelMissing($token->accessToken);
        $this->assertModelExists($otherToken->accessToken);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_token_obtained_from_login_authenticates_a_subsequent_request(): void
    {
        $student = Student::factory()->create();
        $response = $this->postJson('/api/v1/login', [
            'identifier' => $student->codigo_universitario,
            'password' => 'password',
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('data.token'))->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('data.id', $student->user_id);
    }

    public function test_returns_401_for_expired_tokens(): void
    {
        $this->freezeTime();
        $user = User::factory()->coordinator()->create();
        $token = $user->createToken('expired', ['*'], now()->subSecond())->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_legacy_tokens_expire_after_eight_hours_even_without_expires_at(): void
    {
        $this->freezeTime();
        $user = User::factory()->coordinator()->create();
        $token = $user->createToken('legacy')->plainTextToken;
        $this->travel(480)->minutes();

        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_returns_403_and_revokes_tokens_after_user_deactivation(): void
    {
        $user = User::factory()->coordinator()->create();
        $token = $user->createToken('issued-before-deactivation')->plainTextToken;
        $user->update(['estado' => RecordStatus::INACTIVO]);

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_403_and_revokes_tokens_after_profile_deactivation(): void
    {
        $student = Student::factory()->create();
        $token = $student->user->createToken('test')->plainTextToken;
        $student->update(['estado' => RecordStatus::INACTIVO]);

        $this->withToken($token)->getJson('/api/v1/student/pre-enrollments')->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_403_for_existing_token_without_student_profile(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('legacy')->plainTextToken;

        $this->withToken($token)->getJson('/api/v1/me')->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[TestWith(['GET', '/api/v1/me'])]
    #[TestWith(['POST', '/api/v1/logout'])]
    public function test_returns_401_for_unauthenticated_requests(string $method, string $uri): void
    {
        $this->json($method, $uri)->assertUnauthorized();
    }

    public function test_returns_429_after_five_login_attempts_and_recovers_after_one_minute(): void
    {
        $this->freezeTime();
        $payload = ['identifier' => 'missing@unitru.edu.pe', 'password' => 'incorrect-password'];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/login', $payload)->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', $payload)->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->travel(61)->seconds();
        $this->postJson('/api/v1/login', $payload)->assertUnauthorized();
    }

    public function test_legacy_email_field_cannot_bypass_the_identifier_rate_limit(): void
    {
        $this->freezeTime();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/login', [
                'identifier' => 'missing@unitru.edu.pe', 'password' => 'incorrect-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', [
            'correo_institucional' => 'missing@unitru.edu.pe', 'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }

    public function test_login_does_not_log_passwords_hashes_or_tokens(): void
    {
        $student = Student::factory()->create();
        Log::spy();

        $this->postJson('/api/v1/login', [
            'identifier' => $student->codigo_universitario, 'password' => 'password',
        ])->assertOk()->assertJsonMissingPath('data.user.password');

        Log::shouldNotHaveReceived('log');
        Log::shouldNotHaveReceived('debug');
        Log::shouldNotHaveReceived('info');
        Log::shouldNotHaveReceived('notice');
        Log::shouldNotHaveReceived('warning');
        Log::shouldNotHaveReceived('error');
        Log::shouldNotHaveReceived('critical');
        Log::shouldNotHaveReceived('alert');
        Log::shouldNotHaveReceived('emergency');
    }

    public function test_rate_limit_is_shared_with_web_login_even_if_an_extra_identifier_is_sent(): void
    {
        $this->freezeTime();
        Sleep::fake();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/login', [
                'identifier' => 'missing@unitru.edu.pe', 'password' => 'incorrect-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/login', [
            'correo_institucional' => 'missing@unitru.edu.pe',
            'identifier' => 'misleading-identifier',
            'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }

    public function test_ip_rate_limit_prevents_bypassing_throttle_by_changing_identifiers(): void
    {
        $this->freezeTime();
        Sleep::fake();

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson('/api/v1/login', [
                'identifier' => 'missing-'.$attempt.'@unitru.edu.pe', 'password' => 'incorrect-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/v1/login', [
            'identifier' => 'another@unitru.edu.pe', 'password' => 'incorrect-password',
        ])->assertTooManyRequests();
    }
}
