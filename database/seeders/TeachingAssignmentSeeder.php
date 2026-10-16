<?php

namespace Database\Seeders;

use App\Enums\AcademicPeriodStatus;
use App\Models\AcademicPeriod;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\User;
use App\UseCases\Teaching\ManageTeachingAssignment;
use Illuminate\Database\Seeder;

class TeachingAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }
        $this->call(TeacherSeeder::class);
        $teacher = Teacher::query()->where('codigo_institucional', 'DOC-DEMO')->where('estado', 'ACTIVO')->first();
        $actor = User::query()->where('rol', 'COORDINADOR')->where('estado', 'ACTIVO')->orderBy('id')->first();
        $period = AcademicPeriod::query()->where('estado', '!=', AcademicPeriodStatus::CERRADO)->orderByDesc('fecha_inicio')->first();
        if ($teacher === null || $actor === null || $period === null || $teacher->teachingAssignments()->where('academic_period_id', $period->id)->exists()) {
            return;
        }
        $course = Course::query()->where('estado', 'ACTIVO')->where('horas_teoria', '>=', 1)
            ->whereDoesntHave('teachingAssignments', fn ($query) => $query->where('academic_period_id', $period->id))->orderBy('id')->first();
        if ($course !== null && ($teacher->max_horas_semanales === null || (float) $teacher->max_horas_semanales >= 1)) {
            app(ManageTeachingAssignment::class)->save(['academic_period_id' => $period->id, 'teacher_id' => $teacher->id,
                'course_id' => $course->id, 'horas_teoria_asignadas' => '1.00'], $actor);
        }
    }
}
