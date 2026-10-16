<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\AppServiceProvider;
use Database\Seeders\AcademicHistorySeeder;
use Database\Seeders\AcademicPeriodSeeder;
use Database\Seeders\CourseSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\TeacherSeeder;
use Database\Seeders\TeachingAssignmentSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_demonstration_seeder_refuses_production_including_direct_invocation(): void
    {
        $this->app['env'] = 'production';
        try {
            foreach ([DatabaseSeeder::class, AcademicPeriodSeeder::class, CourseSeeder::class, UserSeeder::class,
                StudentSeeder::class, AcademicHistorySeeder::class, TeacherSeeder::class, TeachingAssignmentSeeder::class] as $seeder) {
                $this->artisan('db:seed', ['--class' => $seeder, '--force' => true])->assertSuccessful();
            }

            foreach (['users', 'students', 'courses', 'academic_periods', 'academic_histories', 'teachers', 'teaching_assignments'] as $table) {
                $this->assertDatabaseCount($table, 0);
            }
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_development_seeding_is_repeatable_without_resetting_existing_passwords_or_records(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::query()->where('correo_institucional', 'coordinador@unitru.edu.pe')->sole();
        $user->update(['password' => 'changed-demo-password']);
        $hash = $user->password;

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($hash, $user->fresh()->password);
        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('academic_periods', 3);
        $this->assertDatabaseCount('academic_histories', 2);
    }

    public function test_destructive_commands_are_prohibited_when_the_application_is_not_testing(): void
    {
        User::factory()->create();
        $this->app['env'] = 'production';
        $provider = new AppServiceProvider($this->app);
        try {
            $provider->boot();
            $this->artisan('db:wipe', ['--force' => true])->assertFailed();

            $this->assertDatabaseCount('users', 1);
        } finally {
            $this->app['env'] = 'testing';
            $provider->boot();
        }
    }
}
