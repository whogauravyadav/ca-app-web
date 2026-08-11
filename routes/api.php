<?php

use App\Http\Controllers\Api\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\Admin\ContentController;
use App\Http\Controllers\Api\Admin\QuizAdminController;
use App\Http\Controllers\Api\Admin\NotificationAdminController;
use App\Http\Controllers\Api\Admin\SubscriptionAdminController;
use App\Http\Controllers\Api\Mobile\ArticleController;
use App\Http\Controllers\Api\Mobile\AuthController as MobileAuthController;
use App\Http\Controllers\Api\Mobile\BookmarkController;
use App\Http\Controllers\Api\Mobile\NotificationController;
use App\Http\Controllers\Api\Mobile\QuizController;
use App\Http\Controllers\Api\Mobile\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mobile API — /api/mobile/*
|--------------------------------------------------------------------------
*/
Route::prefix('mobile')->group(function () {
    Route::post('/register', [MobileAuthController::class, 'register']);
    Route::post('/login', [MobileAuthController::class, 'login']);

    Route::get('/categories', [ArticleController::class, 'categories']);
    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/{slug}', [ArticleController::class, 'show']);
    Route::get('/quizzes', [QuizController::class, 'index']);
    Route::get('/quizzes/{id}', [QuizController::class, 'show']);
    Route::get('/plans', [SubscriptionController::class, 'plans']);
    Route::get('/config', [SubscriptionController::class, 'appConfig']);

    // Device tokens + inbox (token register works for guests; optional auth attaches user)
    Route::post('/device-tokens', [NotificationController::class, 'registerToken']);
    Route::delete('/device-tokens', [NotificationController::class, 'unregisterToken']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [MobileAuthController::class, 'logout']);
        Route::get('/profile', [MobileAuthController::class, 'profile']);
        Route::patch('/profile', [MobileAuthController::class, 'updateProfile']);
        Route::post('/profile/password', [MobileAuthController::class, 'changePassword']);

        Route::post('/quizzes/{id}/submit', [QuizController::class, 'submit']);

        Route::get('/bookmarks', [BookmarkController::class, 'index']);
        Route::post('/bookmarks', [BookmarkController::class, 'store']);
        Route::delete('/bookmarks/{articleId}', [BookmarkController::class, 'destroy']);

        Route::get('/subscription', [SubscriptionController::class, 'current']);
        Route::post('/subscription/activate', [SubscriptionController::class, 'activate']);
        Route::post('/reading-progress', [SubscriptionController::class, 'saveProgress']);

        Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('/device-tokens/auth', [NotificationController::class, 'registerToken']);
    });
});

/*
|--------------------------------------------------------------------------
| Admin API — /api/admin/*
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'admin'])->group(function () {
        Route::get('/me', [AdminAuthController::class, 'me']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);
        Route::get('/dashboard', [ContentController::class, 'dashboard']);

        Route::get('/categories', [ContentController::class, 'categories']);
        Route::post('/categories', [ContentController::class, 'storeCategory']);
        Route::put('/categories/{id}', [ContentController::class, 'updateCategory']);
        Route::delete('/categories/{id}', [ContentController::class, 'destroyCategory']);

        Route::get('/articles', [ContentController::class, 'articles']);
        Route::post('/articles', [ContentController::class, 'storeArticle']);
        Route::put('/articles/{id}', [ContentController::class, 'updateArticle']);
        Route::delete('/articles/{id}', [ContentController::class, 'destroyArticle']);
        Route::post('/articles/{id}/publish', [ContentController::class, 'publishArticle']);
        Route::post('/upload/image', [ContentController::class, 'uploadImage']);

        Route::get('/quizzes', [QuizAdminController::class, 'index']);
        Route::post('/quizzes', [QuizAdminController::class, 'store']);
        Route::get('/quizzes/{id}', [QuizAdminController::class, 'show']);
        Route::put('/quizzes/{id}', [QuizAdminController::class, 'update']);
        Route::delete('/quizzes/{id}', [QuizAdminController::class, 'destroy']);

        Route::get('/plans', [SubscriptionAdminController::class, 'plans']);
        Route::post('/plans', [SubscriptionAdminController::class, 'storePlan']);
        Route::put('/plans/{id}', [SubscriptionAdminController::class, 'updatePlan']);
        Route::delete('/plans/{id}', [SubscriptionAdminController::class, 'destroyPlan']);

        Route::get('/subscribers', [SubscriptionAdminController::class, 'subscribers']);
        Route::post('/users/{userId}/grant-subscription', [SubscriptionAdminController::class, 'grant']);
        Route::post('/users/{userId}/revoke-subscription', [SubscriptionAdminController::class, 'revoke']);

        Route::get('/notifications', [NotificationAdminController::class, 'index']);
        Route::get('/notifications/settings', [NotificationAdminController::class, 'settings']);
        Route::put('/notifications/settings', [NotificationAdminController::class, 'updateSettings']);
        Route::post('/notifications/send', [NotificationAdminController::class, 'send']);
    });
});
