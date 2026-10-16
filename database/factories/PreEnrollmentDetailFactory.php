<?php

namespace Database\Factories;

use App\Enums\SchedulePreference;
use App\Models\Course;
use App\Models\PreEnrollmentDetail;
use App\Models\PreEnrollmentRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreEnrollmentDetail>
 */
class PreEnrollmentDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pre_enrollment_request_id' => PreEnrollmentRequest::factory(),
            'course_id' => Course::factory(),
            'preferencia_turno' => SchedulePreference::SIN_PREFERENCIA,
        ];
    }
}
