<?php

use App\Http\Controllers\Api\Admin\CourseManagementController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\VoucherManagementController;
use App\Http\Controllers\Api\AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\Mentor\MentorCurriculumController;
use App\Http\Controllers\Api\Mentor\MentorDashboardController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\StudentPaymentController;
use App\Http\Controllers\Api\StudentDashboardController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Route;

Route::get('/health', function (): JsonResponse {
    return response()->json([
        'status' => 'ok',
        'service' => 'elearning-backend',
        'timestamp' => now()->toISOString(),
    ]);
});

Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{slug}', [CourseController::class, 'show']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::patch('/me/profile', [ProfileController::class, 'update']);
    Route::post('/me/avatar', [ProfileController::class, 'uploadAvatar']);
    Route::post('/me/password', [ProfileController::class, 'updatePassword']);

    Route::middleware('role:student,mentor,admin')->group(function (): void {
        Route::get('/student/dashboard', [StudentDashboardController::class, 'show']);
        Route::get('/student/transactions', [StudentPaymentController::class, 'index']);
        Route::post('/student/transactions/checkout', [StudentPaymentController::class, 'checkout']);
        Route::post('/student/transactions/{transaction}/pay', [StudentPaymentController::class, 'pay']);
    });

    Route::middleware('role:mentor,admin')->prefix('mentor')->group(function (): void {
        Route::get('/dashboard', [MentorDashboardController::class, 'show']);
        Route::get('/courses/{course}', [MentorDashboardController::class, 'showCourse']);
        Route::put('/courses/{course}', [MentorCurriculumController::class, 'updateCourse']);
        Route::post('/courses/{course}/trailer/upload', [MentorCurriculumController::class, 'uploadTrailer']);

        Route::post('/courses/{course}/modules', [MentorCurriculumController::class, 'storeModule']);
        Route::put('/modules/{module}', [MentorCurriculumController::class, 'updateModule']);
        Route::delete('/modules/{module}', [MentorCurriculumController::class, 'destroyModule']);

        Route::post('/modules/{module}/lessons', [MentorCurriculumController::class, 'storeLesson']);
        Route::put('/lessons/{lesson}', [MentorCurriculumController::class, 'updateLesson']);
        Route::delete('/lessons/{lesson}', [MentorCurriculumController::class, 'destroyLesson']);
    });

    Route::middleware('role:admin')->prefix('admin')->group(function (): void {
        Route::get('/dashboard', [AdminDashboardController::class, 'show']);

        Route::get('/courses', [CourseManagementController::class, 'index']);
        Route::post('/courses', [CourseManagementController::class, 'store']);
        Route::put('/courses/{course}', [CourseManagementController::class, 'update']);
        Route::delete('/courses/{course}', [CourseManagementController::class, 'destroy']);

        Route::get('/users', [UserManagementController::class, 'index']);
        Route::post('/users', [UserManagementController::class, 'store']);
        Route::patch('/users/{user}/role', [UserManagementController::class, 'updateRole']);
        Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword']);

        Route::get('/vouchers', [VoucherManagementController::class, 'index']);
        Route::post('/vouchers', [VoucherManagementController::class, 'store']);
        Route::put('/vouchers/{voucher}', [VoucherManagementController::class, 'update']);
        Route::delete('/vouchers/{voucher}', [VoucherManagementController::class, 'destroy']);
    });
});
