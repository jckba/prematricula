<?php

use App\Http\Controllers\Api\AcademicHistoryController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Coordinator\AcademicHistoryImportController;
use App\Http\Controllers\Api\Coordinator\AcademicPeriodController;
use App\Http\Controllers\Api\Coordinator\AccountController;
use App\Http\Controllers\Api\Coordinator\CurriculumController;
use App\Http\Controllers\Api\Coordinator\PreEnrollmentController as CoordinatorPreEnrollmentController;
use App\Http\Controllers\Api\Coordinator\PreEnrollmentWindowController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\PreEnrollmentRevisionController;
use App\Http\Controllers\Api\Student\PreEnrollmentController;
use App\Http\Controllers\Api\Student\PreEnrollmentCorrectionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/login', [
        AuthController::class,
        'login',
    ])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'user.active'])->group(function () {

        Route::get('courses', [CourseController::class, 'index'])->name('api.v1.courses.index');
        Route::get('courses/{course}', [CourseController::class, 'show'])->name('api.v1.courses.show');
        Route::get('academic-periods', [AcademicPeriodController::class, 'index']);
        Route::get('academic-periods/{period}', [AcademicPeriodController::class, 'show']);
        Route::get('academic-periods/{period}/windows', [PreEnrollmentWindowController::class, 'index']);

        Route::get('/me', [
            AuthController::class,
            'me',
        ]);

        Route::post('/logout', [
            AuthController::class,
            'logout',
        ]);

        Route::prefix('student')
            ->middleware('role:ESTUDIANTE',
                'student.profile')
            ->group(function () {

                Route::get('academic-history', [AcademicHistoryController::class, 'index']);
                Route::post('pre-enrollments/{preEnrollment}/corrections', [PreEnrollmentCorrectionController::class, 'store']);
                Route::post('pre-enrollment', [PreEnrollmentController::class, 'store']);
                Route::get('pre-enrollments/{preEnrollment}', [PreEnrollmentController::class, 'detail']);
                Route::get('pre-enrollments/{preEnrollment}/revisions', [PreEnrollmentRevisionController::class, 'index']);
                Route::get('pre-enrollments/{preEnrollment}/receipt', [PreEnrollmentRevisionController::class, 'currentReceipt']);
                Route::get('pre-enrollments/{preEnrollment}/revisions/{revision}/receipt', [PreEnrollmentRevisionController::class, 'receipt']);

                Route::get(
                    '/pre-enrollment/available-courses',
                    [
                        PreEnrollmentController::class,
                        'availableCourses',
                    ]
                );

                Route::get(
                    '/pre-enrollment',
                    [
                        PreEnrollmentController::class,
                        'show',
                    ]
                );

                Route::get(
                    '/pre-enrollments',
                    [
                        PreEnrollmentController::class,
                        'history',
                    ]
                );

                Route::post(
                    '/pre-enrollment/courses',
                    [
                        PreEnrollmentController::class,
                        'addCourse',
                    ]
                );

                Route::delete(
                    '/pre-enrollment/courses/{detail}',
                    [
                        PreEnrollmentController::class,
                        'removeCourse',
                    ]
                );

                Route::patch(
                    '/pre-enrollment/courses/{detail}/preference',
                    [
                        PreEnrollmentController::class,
                        'updatePreference',
                    ]
                );

                Route::post(
                    '/pre-enrollment/submit',
                    [
                        PreEnrollmentController::class,
                        'submit',
                    ]
                );
            });

        Route::prefix('coordinator')
            ->middleware('role:COORDINADOR')
            ->group(function () {

                Route::post('academic-periods', [AcademicPeriodController::class, 'store']);
                Route::patch('academic-periods/{period}', [AcademicPeriodController::class, 'update']);
                Route::delete('academic-periods/{period}', [AcademicPeriodController::class, 'destroy']);
                Route::get('academic-periods/{period}/demand', [AcademicPeriodController::class, 'demand']);
                Route::post('academic-periods/{period}/windows', [PreEnrollmentWindowController::class, 'store']);
                Route::get('academic-history', [AcademicHistoryController::class, 'index']);
                Route::get('academic-history/template', [AcademicHistoryImportController::class, 'template']);
                Route::post('academic-history/validate', [AcademicHistoryImportController::class, 'validateFile']);
                Route::post('academic-history/import', [AcademicHistoryImportController::class, 'import']);
                Route::get('academic-history/imports', [AcademicHistoryImportController::class, 'index']);
                Route::get('academic-history/imports/{import}', [AcademicHistoryImportController::class, 'show']);
                Route::get('pre-enrollments/{preEnrollment}/revisions', [PreEnrollmentRevisionController::class, 'index']);
                Route::get('pre-enrollments/{preEnrollment}/receipt', [PreEnrollmentRevisionController::class, 'currentReceipt']);
                Route::get('pre-enrollments/{preEnrollment}/revisions/{revision}/receipt', [PreEnrollmentRevisionController::class, 'receipt']);

                Route::patch('courses/{course}', [CourseController::class, 'update'])->name('api.v1.coordinator.courses.update');
                Route::get('curriculum/template', [CurriculumController::class, 'template'])->name('api.v1.coordinator.curriculum.template');
                Route::post('curriculum/validate', [CurriculumController::class, 'validateFile'])->name('api.v1.coordinator.curriculum.validate');
                Route::post('curriculum/import', [CurriculumController::class, 'import'])->name('api.v1.coordinator.curriculum.import');
                Route::get('curriculum/export', [CurriculumController::class, 'export'])->name('api.v1.coordinator.curriculum.export');

                Route::apiResource('accounts', AccountController::class)
                    ->only(['index', 'show', 'store', 'update']);

                Route::get(
                    '/pre-enrollments',
                    [
                        CoordinatorPreEnrollmentController::class,
                        'index',
                    ]
                );

                Route::get(
                    '/pre-enrollments/{preEnrollment}',
                    [
                        CoordinatorPreEnrollmentController::class,
                        'show',
                    ]
                );

            });

    });
});
