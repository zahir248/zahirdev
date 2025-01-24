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

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'store_name' => 'nullable|string|max:255',
            'total_amount' => 'nullable|numeric',
            'date' => 'nullable|date',
        ]);

        $receipt = Receipt::findOrFail($id);
        $receipt->update($validated);

        return response()->json(['message' => 'Receipt updated successfully', 'receipt' => $receipt], 200);
    }

    public function destroy($id)
    {
        // Find the receipt by its ID
        $receipt = Receipt::find($id);

        // Check if the receipt exists
        if (!$receipt) {
            return response()->json(['message' => 'Receipt not found'], 404);
        }

        // Delete the receipt
        $receipt->delete();

        // Return a success response
        return response()->json(['message' => 'Receipt deleted successfully']);
    }
}
