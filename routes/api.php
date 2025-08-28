<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\V1\Auth\AuthController;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use Laravel\Passport\Http\Controllers\AuthorizationController;


Route::group(['prefix' => '/oauth'], function () {
    Route::post('token', [AccessTokenController::class, 'issue']);
    Route::post('authorize', [AuthorizationController::class, 'authorize']);
    Route::post('refresh', [AccessTokenController::class, 'refresh']);
    Route::post('revoke', [AccessTokenController::class, 'revoke']);
});


Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:api')->group(function () {
        Route::prefix('auth')->group(function () {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);
            Route::post('refresh', [AuthController::class, 'refresh']);
        });

    });
});