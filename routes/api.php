<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Student\PreEnrollmentController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Coordinator\PreEnrollmentController as CoordinatorPreEnrollmentController;

Route::prefix('v1')->group(function () {
    Route::post('/login', [
        AuthController::class,
        'login',
    ]);

    Route::middleware('auth:sanctum')->group(function () {

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

                Route::post(
                    '/pre-enrollments/{preEnrollment}/approve',
                    [
                        CoordinatorPreEnrollmentController::class,
                        'approve',
                    ]
                );

                Route::post(
                    '/pre-enrollments/{preEnrollment}/reject',
                    [
                        CoordinatorPreEnrollmentController::class,
                        'reject',
                    ]
                );
            });

    });
});
