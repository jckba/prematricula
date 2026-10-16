<?php

namespace Tests\Feature\Policies;

use App\Enums\CoordinatorPosition;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class CoursePolicyTest extends TestCase
{
    #[TestWith([CoordinatorPosition::DIRECTOR_ESCUELA])]
    #[TestWith([CoordinatorPosition::SECRETARIA])]
    public function test_both_coordinator_positions_have_identical_catalog_permissions(CoordinatorPosition $position): void
    {
        $user = User::factory()->coordinator($position)->make();
        $course = Course::factory()->inactive()->make();
        $gate = Gate::forUser($user);

        $this->assertTrue($gate->allows('viewAny', Course::class));
        $this->assertTrue($gate->allows('view', $course));
        $this->assertTrue($gate->allows('manageCurriculum', Course::class));
        $this->assertTrue($gate->allows('update', $course));
        $this->assertFalse($gate->allows('delete', $course));
    }

    public function test_students_can_read_active_courses_but_cannot_manage_the_curriculum(): void
    {
        $user = User::factory()->make();
        $user->setRelation('student', Student::factory()->make(['user_id' => 1]));
        $active = Course::factory()->make();
        $inactive = Course::factory()->inactive()->make();
        $gate = Gate::forUser($user);

        $this->assertTrue($gate->allows('viewAny', Course::class));
        $this->assertTrue($gate->allows('view', $active));
        $this->assertFalse($gate->allows('view', $inactive));
        $this->assertSame(404, $gate->inspect('view', $inactive)->status());
        $this->assertFalse($gate->allows('manageCurriculum', Course::class));
        $this->assertFalse($gate->allows('update', $active));
        $this->assertFalse($gate->allows('delete', $active));
    }

    public function test_inactive_coordinators_cannot_manage_or_consult_the_catalog(): void
    {
        $user = User::factory()->coordinator()->inactive()->make();
        $course = Course::factory()->make();
        $gate = Gate::forUser($user);

        $this->assertFalse($gate->allows('viewAny', Course::class));
        $this->assertFalse($gate->allows('view', $course));
        $this->assertFalse($gate->allows('manageCurriculum', Course::class));
        $this->assertFalse($gate->allows('update', $course));
    }
}
