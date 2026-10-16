<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call([
            AcademicPeriodSeeder::class,
            CourseSeeder::class,
            UserSeeder::class,
            StudentSeeder::class,
            AcademicHistorySeeder::class,
        ]);
    }
}
