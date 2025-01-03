<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\ServiceLog\VehicleController;
use App\Http\Controllers\API\ServiceLog\ServiceRecordController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/// auth ///

Route::post('register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

/// ServiceLog ///

// Vehicle //

Route::middleware('auth:sanctum')->get('/vehicles', [VehicleController::class, 'index']);

Route::middleware('auth:sanctum')->post('/vehicle/store', [VehicleController::class, 'store']);

Route::middleware('auth:sanctum')->delete('/vehicle/{id}', [VehicleController::class, 'destroy']);

Route::middleware('auth:sanctum')->get('/vehicle/{id}', [VehicleController::class, 'show']);

Route::middleware('auth:sanctum')->put('/vehicle/{id}', [VehicleController::class, 'update']);

Route::middleware('auth:sanctum')->get('/vehicle/{id}/export-pdf', [VehicleController::class, 'exportPDF']);

// Service Record //

Route::middleware('auth:sanctum')->get('/service-history/{vehicleId}', [ServiceRecordController::class, 'index']);

Route::middleware('auth:sanctum')->post('/add-service-history/store/{vehicleId}', [ServiceRecordController::class, 'store']);

Route::middleware('auth:sanctum')->delete('/service-history/{id}', [ServiceRecordController::class, 'destroy']);

Route::middleware('auth:sanctum')->put('/service-history/{id}', [ServiceRecordController::class, 'update']);

