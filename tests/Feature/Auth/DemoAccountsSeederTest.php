<?php

namespace Tests\Feature\Auth;

use App\Enums\CoordinatorPosition;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class DemoAccountsSeederTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['local'])]
    #[TestWith(['testing'])]
    public function test_creates_one_student_director_and_secretary_and_can_run_again(string $environment): void
    {
        $this->app->instance('env', $environment);

        $this->seed([UserSeeder::class, StudentSeeder::class]);
        $this->seed([UserSeeder::class, StudentSeeder::class]);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseHas('users', ['correo_institucional' => 'coordinador@unitru.edu.pe', 'cargo' => 'DIRECTOR_ESCUELA']);
        $this->assertDatabaseHas('users', ['correo_institucional' => 'secretaria.demo@unitru.edu.pe', 'cargo' => 'SECRETARIA']);
        $this->assertSame('1452700722', Student::sole()->codigo_universitario);
    }

    #[TestWith(['production'])]
    #[TestWith(['staging'])]
    public function test_demo_accounts_are_not_seeded_outside_local_and_testing(string $environment): void
    {
        $this->app->instance('env', $environment);

        foreach ([DatabaseSeeder::class, UserSeeder::class, StudentSeeder::class] as $seeder) {
            $this->artisan('db:seed', ['--class' => $seeder, '--force' => true, '--no-interaction' => true])
                ->assertSuccessful();
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('academic_histories', 0);
    }

    public function test_reseeding_does_not_reset_existing_credentials_status_or_position(): void
    {
        $user = User::factory()->coordinator(CoordinatorPosition::SECRETARIA)->inactive()->create([
            'correo_institucional' => 'coordinador@unitru.edu.pe',
            'password' => 'changed-password',
        ]);
        $this->seed(UserSeeder::class);

        $this->assertTrue(Hash::check('changed-password', $user->refresh()->password));
        $this->assertSame(CoordinatorPosition::SECRETARIA, $user->cargo);
        $this->assertSame('INACTIVO', $user->estado->value);
    }
}
