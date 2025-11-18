<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\InterestController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\BusinessVerificationController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\CircleController;
use App\Http\Controllers\Api\InvitationController;

Route::prefix('v1')->group(function () {

    Route::get('/categories', [InterestController::class, 'index']);

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/check-contact', [AuthController::class, 'checkContact']);

        Route::post('/send-otp', [AuthController::class, 'sendOtp']);
        Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
        Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

        // ✅ ADD THESE NEW ROUTES FOR PASSWORD RESET
        Route::post('/forgot-password/check-account', [AuthController::class, 'checkAccountForReset']);
        Route::post('/forgot-password/send-otp', [AuthController::class, 'sendPasswordResetOtp']);
        Route::post('/forgot-password/verify-otp', [AuthController::class, 'verifyPasswordResetOtp']);
        Route::post('/forgot-password/reset', [AuthController::class, 'resetPassword']);

        Route::get('/interests', [InterestController::class, 'index']);
    });

    Route::middleware('auth:api')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::get('/users', [AuthController::class, 'listUsers']);
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
            Route::get('/profile', [AuthController::class, 'userProfile']);


            // Save each step
            Route::post('/business-verification/step/{step}', [BusinessVerificationController::class, 'saveStep']);

            // Get cached data (for resume)
            Route::get('/business-verification/cache', [BusinessVerificationController::class, 'getCache']);

            // Submit final data
            Route::post('/business-verification/submit', [BusinessVerificationController::class, 'submit']);

            // Delete draft (optional)
            Route::delete('/business-verification/cache', [BusinessVerificationController::class, 'clearCache']);

            Route::post('/business-verification/upload', [BusinessVerificationController::class, 'uploadDocument']);
            // GET business profile
            Route::get('/business-verification/profile', [BusinessVerificationController::class, 'getBusinessProfile']);

            Route::get('/business-verification/my-businesses', [BusinessVerificationController::class, 'myBusinesses']);

            Route::get('/business-verification/profile/{id}', [BusinessVerificationController::class, 'getProfile']);

            Route::put('/business-verification/{id}/description', [BusinessVerificationController::class, 'updateDescription']);

            Route::put('/business-verification/{id}/bank-details', [BusinessVerificationController::class, 'updateBankDetails']);

            // Circles
            Route::post('/circles/create', [CircleController::class, 'create']);

            Route::get('/circles/{id}', [CircleController::class, 'show']);

            // Invitations
            Route::post('/circles/{circle_id}/invite', [InvitationController::class, 'sendInvitations']);
            Route::get('/circles/requests', [InvitationController::class, 'adminRequests']);

            Route::post('/circles/requests/{request_id}/approve', [InvitationController::class, 'approve']);
            Route::post('/circles/requests/{request_id}/reject', [InvitationController::class, 'reject']);

            Route::post('/circles/{circle_id}/check-status', [CircleController::class, 'checkStatus']);

            Route::post('/circles/{id}/invitations/accept', [InvitationController::class, 'accept']);
            Route::post('/circles/{id}/invitations/decline', [InvitationController::class, 'decline']);
        });
        Route::get('/posts', [PostController::class, 'index']);
        Route::post('/posts', [PostController::class, 'store']);
        Route::put('/posts/{id}', [PostController::class, 'update']);
        Route::delete('/posts/{id}', [PostController::class, 'destroy']);

        Route::post('/posts/{id}/increment-views', [PostController::class, 'incrementViews']);
    });
    Route::post('/check-username', [AuthController::class, 'checkUsername']); // ✅ NEW ROUTE

    Route::post('/upload-profile-image', [ImageController::class, 'uploadProfileImage']);
    Route::prefix('auth')->group(function () {
        Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    });
});


Route::get('/health', function () {
    return response()->json([
        'status' => 'OK',
        'message' => 'Dealzta API is running',
        'timestamp' => now()->toISOString(),
    ]);
});
