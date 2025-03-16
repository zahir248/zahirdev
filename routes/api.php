<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\API\AuthController;

use App\Http\Controllers\API\ServiceLog\VehicleController;
use App\Http\Controllers\API\ServiceLog\ServiceRecordController;

use App\Http\Controllers\API\ReceiptLog\ReceiptController;
use App\Http\Controllers\API\ReceiptLog\ReceiptItemController;
use App\Http\Controllers\API\ReceiptLog\UserController;

use App\Http\Controllers\API\MusicLog\MusicController;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/// auth ///

Route::post('register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->put('user/profile/update', [AuthController::class, 'update']);

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


/// ReceiptLog ///

// Receipt //

Route::get('/receipts/{userId}', [ReceiptController::class, 'getReceipts']);

Route::put('/receipts/{id}', [ReceiptController::class, 'update']);

Route::delete('/receipts/{id}', [ReceiptController::class, 'destroy']);

Route::post('/receipts', [ReceiptController::class, 'store']);

Route::get('/reports', [ReceiptController::class, 'getReports']);

Route::post('/receipts/auto', [ReceiptController::class, 'storeAuto']);

// Receipt Item // 

Route::get('/receipts/receipt-items/{id}', [ReceiptItemController::class, 'getItems']);

Route::put('/receipts/{receiptId}/items/{itemId}', [ReceiptItemController::class, 'update']);

Route::delete('/receipts/{receiptId}/items/{itemId}', [ReceiptItemController::class, 'destroy']);

Route::post('/receipts/{receiptId}/items', [ReceiptItemController::class, 'store']);

// User // 

Route::post('/update-profile', [UserController::class, 'updateProfile']);


/// MusicLog ///

// Music //

Route::post('/download-mp3', [MusicController::class, 'downloadMP3']);

Route::get('/check-yt-dlp', function () {
    $ytDlpPath = base_path('bin/yt-dlp.exe');
    return file_exists($ytDlpPath) ? 'File exists' : 'File not found';
});

Route::get('/debug-yt-dlp', function () {
    $ytDlpPath = base_path('bin/yt-dlp.exe');
    return $ytDlpPath . ' - ' . (file_exists($ytDlpPath) ? 'File exists' : 'File not found');
});

Route::get('/test-shell', function () {
    if (!function_exists('shell_exec')) {
        return "shell_exec() is completely disabled!";
    }

    $disabledFunctions = explode(',', ini_get('disable_functions'));
    if (in_array('shell_exec', $disabledFunctions)) {
        return "shell_exec() is disabled in php.ini!";
    }

    return "shell_exec() is enabled!";
});

