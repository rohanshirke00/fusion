<?php

use App\Http\Controllers\VideoController;
use App\Http\Controllers\WatermarkController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Route::post('merge-videos', [VideoController::class, 'mergeVideos']);
Route::post('merge-videos', [WatermarkController::class, 'mergeVideos']);