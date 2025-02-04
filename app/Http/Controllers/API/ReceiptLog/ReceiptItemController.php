<?php

namespace App\Http\Controllers\API\ReceiptLog;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

use App\Models\User;
use App\Models\ReceiptLog\ReceiptItem;
use App\Models\ReceiptLog\Receipt;

class ReceiptItemController extends Controller
{
    public function getItems($id)
    {
        // Fetch the receipt items based on the receipt ID
        $items = ReceiptItem::where('receipt_id', $id)->get();

        // Return the items as JSON
        return response()->json($items);
    }

    public function update(Request $request, $receiptId, $itemId)
    {
        // Validate receipt and item
        $item = ReceiptItem::where('id', $itemId)
            ->where('receipt_id', $receiptId)
            ->firstOrFail();

        // Fetch the receipt directly to ensure it's up-to-date
        $receipt = $item->receipt; // Assuming you have a relationship defined

        // Store old values for calculation
        $oldQuantity = $item->quantity;
        $oldPrice = $item->price;

        // Calculate the old item's total price (quantity * price)
        $oldItemTotal = $oldQuantity * $oldPrice;

        // Update the item
        $item->update([
            'item_name' => $request->input('item_name'),
            'quantity' => $request->input('quantity'),
            'price' => $request->input('price'),
        ]);

        // Calculate the new item's total price (quantity * price)
        $newItemTotal = $item->quantity * $item->price;

        // Determine the price difference (positive or negative)
        $priceDifference = $newItemTotal - $oldItemTotal;

        // Update the receipt's total_amount dynamically
        $receipt->total_amount += $priceDifference;
        $receipt->save();

        // Return a JSON response
        return response()->json([
            'item' => $item,
            'receipt' => $receipt,
            'message' => 'Item and receipt updated successfully.',
        ]);
    }

    public function destroy($receiptId, $itemId)
    {
        // Retrieve the item to be deleted by both receiptId and itemId
        $item = ReceiptItem::where('id', $itemId)
            ->where('receipt_id', $receiptId)
            ->firstOrFail();

        // Get the associated receipt
        $receipt = $item->receipt;

        // Calculate the total price of the item to be deleted
        $itemTotal = $item->price * $item->quantity;

        // Subtract the item's total price from the receipt's total amount
        $receipt->total_amount -= $itemTotal;

        // Save the updated receipt total amount
        $receipt->save();

        // Delete the item
        $item->delete();

        // Return a success response
        return response()->json(['message' => 'Item deleted successfully']);
    }

    public function store(Request $request, $receiptId)
    {
        // Validate the incoming request
        $validatedData = $request->validate([
            'item_name' => 'string|max:255',
            'quantity' => 'integer|min:1',
            'price' => 'numeric|min:0'
        ]);

        DB::beginTransaction();
        try {
            // Verify that the receipt exists
            $receipt = Receipt::findOrFail($receiptId);

            // Calculate item total
            $itemTotal = $validatedData['quantity'] * $validatedData['price'];

            // Create the receipt item
            $receiptItem = ReceiptItem::create([
                'receipt_id' => $receiptId,
                'item_name' => $validatedData['item_name'],
                'quantity' => $validatedData['quantity'],
                'price' => $validatedData['price']
            ]);

            // Update the receipt's total amount
            $receipt->total_amount += $itemTotal;
            $receipt->save();

            DB::commit();

            // Return the created item with a 201 status code
            return response()->json([
                'item' => $receiptItem,
                'updated_receipt_total' => $receipt->total_amount
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Handle any errors
            return response()->json([
                'message' => 'Error creating receipt item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
