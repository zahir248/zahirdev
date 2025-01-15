<?php

namespace App\Models\ReceiptLog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReceiptItem extends Model
{
    use HasFactory;

    protected $table = 'receipt_items';

    protected $fillable = [
        'receipt_id', 
        'item_name', 
        'quantity', 
        'price'
    ];

    // Define relationship with Receipt
    public function receipt()
    {
        return $this->belongsTo(Receipt::class);
    }
}
