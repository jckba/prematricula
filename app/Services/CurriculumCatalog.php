<?php

namespace App\Services;

use App\Enums\RecordStatus;
use App\Models\Course;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CurriculumCatalog
{
    public const ACADEMIC_FIELDS = ['nombre', 'creditos', 'ciclo', 'horas_teoria', 'horas_practica', 'horas_laboratorio'];

    /**
     * The transaction lock serializes mutations of the single prerequisite graph,
     * including imports into an initially empty catalogue. Row locks also protect
     * the usage check against concurrent inserts referencing an existing course.
     *
     * @template TResult
     *
     * @param  Closure(Collection<int, Course>): TResult  $operation
     * @return TResult
     */
    public function transaction(Closure $operation): mixed
    {
        return DB::transaction(function () use ($operation): mixed {
            DB::select('SELECT pg_advisory_xact_lock(?)', [82640101]);
            $courses = Course::query()->orderBy('id')->lockForUpdate()->get();
            $courses->load('prerequisites');
            $courses->loadCount(['academicHistories', 'preEnrollmentDetails', 'preEnrollmentRevisions',
                'teachingAssignments' => function (Builder|Relation $assignments): Builder {
                    if ($assignments instanceof Relation) {
                        $assignments = $assignments->getQuery();
                    }

                    return $assignments->withoutGlobalScope(SoftDeletingScope::class);
                }]);

            return $operation($courses);
        }, 3);
    }

    /** @param Collection<int, Course> $courses */
    public function fingerprint(Collection $courses): string
    {
        $data = $courses->sortBy('codigo', SORT_STRING)->map(fn (Course $course): array => [
            'id' => $course->id,
            'codigo' => $course->codigo,
            ...$course->only(self::ACADEMIC_FIELDS),
            'estado' => $course->estado->value,
            'prerrequisitos' => $course->prerequisites->pluck('codigo')->sort(SORT_STRING)->values()->all(),
            'in_use' => $this->isProtected($course),
        ])->values()->all();

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function isProtected(Course $course): bool
    {
        return $course->academic_histories_count > 0 || $course->pre_enrollment_details_count > 0 || $course->pre_enrollment_revisions_count > 0 || $course->teaching_assignments_count > 0;
    }

    /**
     * @param  array<int, array{row: int, codigo: string, nombre: string, creditos: int, ciclo: int, horas_teoria: string, horas_practica: string, horas_laboratorio: string, prerrequisitos: list<string>}>  $rows
     * @param  Collection<int, Course>  $courses
     */
    public function persist(array $rows, Collection $courses): void
    {
        $byCode = $courses->keyBy('codigo');

        foreach ($rows as $row) {
            $course = $byCode->get($row['codigo']);
            $attributes = Arr::only($row, self::ACADEMIC_FIELDS);

            if ($course === null) {
                $course = Course::create($attributes + ['codigo' => $row['codigo'], 'estado' => RecordStatus::ACTIVO]);
                $byCode->put($row['codigo'], $course);
            } else {
                $course->fill($attributes);

                if ($course->isDirty()) {
                    $course->save();
                }
            }
        }

        foreach ($rows as $row) {
            $course = $byCode->get($row['codigo']);
            $prerequisiteIds = array_map(fn (string $code): int => $byCode->get($code)->id, $row['prerrequisitos']);
            sort($prerequisiteIds);
            $currentIds = $course->relationLoaded('prerequisites')
                ? $course->prerequisites->modelKeys()
                : [];
            sort($currentIds);

            if ($currentIds !== $prerequisiteIds) {
                $course->prerequisites()->sync($prerequisiteIds);
                $course->touch();
            }
        }
    }
}
