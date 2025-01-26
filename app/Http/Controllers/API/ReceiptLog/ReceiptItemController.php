<?php

namespace App\Http\Controllers\API\ReceiptLog;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;

use App\Models\User;
use App\Models\ReceiptLog\ReceiptItem;

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

}
