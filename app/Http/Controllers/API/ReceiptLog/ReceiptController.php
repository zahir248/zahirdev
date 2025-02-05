<?php

namespace App\Http\Controllers\API\ReceiptLog;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'store_name' => 'required|string|max:255',
            'date' => 'required|date',
            'userId' => 'required|exists:users,id', // Make sure the user_id exists in the users table
        ]);

        // Map the 'userId' field from the request to 'user_id' in the validated data
        $validatedData['user_id'] = $validatedData['userId'];  // Map 'userId' to 'user_id'
        unset($validatedData['userId']);  // Remove the old 'userId' field

        // Set total_amount to 0
        $validatedData['total_amount'] = 0;

        // Create the receipt with the provided user_id and total_amount
        $receipt = Receipt::create($validatedData);

        return response()->json(['message' => 'Receipt added successfully', 'receipt' => $receipt], 201);
    }

    public function getReports(Request $request)
    {
        $userId = $request->query('user_id');

        if (!$userId) {
            return response()->json(['error' => 'User ID is required'], 400);
        }

        $reports = DB::table('receipts')
            ->select([
                'receipts.id as receipt_id',
                'receipts.store_name',
                'receipts.total_amount',
                'receipts.date',
                'receipt_items.id as item_id',
                'receipt_items.item_name',
                'receipt_items.quantity',
                'receipt_items.price'
            ])
            ->leftJoin('receipt_items', 'receipts.id', '=', 'receipt_items.receipt_id')
            ->where('receipts.user_id', $userId)
            ->orderBy('receipts.date', 'desc')
            ->get();

        // Restructure the data to group items by receipt
        $formattedReports = [];
        foreach ($reports as $row) {
            $receiptId = $row->receipt_id;
            
            if (!isset($formattedReports[$receiptId])) {
                $formattedReports[$receiptId] = [
                    'receipt_id' => $receiptId,
                    'store_name' => $row->store_name,
                    'total_amount' => $row->total_amount,
                    'date' => $row->date,
                    'items' => []
                ];
            }

            if ($row->item_id) {
                $formattedReports[$receiptId]['items'][] = [
                    'item_name' => $row->item_name,
                    'quantity' => $row->quantity,
                    'price' => $row->price
                ];
            }
        }

        // Convert associative array to indexed array
        $formattedReports = array_values($formattedReports);

        return response()->json($formattedReports);
    }

}
