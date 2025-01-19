<?php

namespace App\Http\Controllers\API\ReceiptLog;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

use App\Models\User;
use App\Models\ReceiptLog\Receipt;

class ReceiptController extends Controller
{
    public function getReceipts($userId)
    {
        try {
            // Fetch receipts for the given user, ordered by date
            $receipts = Receipt::where('user_id', $userId)
                ->orderBy('date', 'desc')
                ->get();

            $responseData = [
                'status' => 200,
                'receipts' => $receipts,
            ];

            if ($receipts->isEmpty()) {
                $responseData['message'] = 'No receipts found for this user.';
            }

            return response()->json($responseData);
            
        } catch (\Exception $e) {
            // Log the error if something goes wrong
            Log::error('Error fetching receipts: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'Error fetching receipts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
