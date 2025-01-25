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
    
}
