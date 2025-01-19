<?php

namespace App\Models\ReceiptLog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\User;

class Receipt extends Model
{
    use HasFactory;

    protected $table = 'receipts';

    protected $fillable = [
        'user_id', 
        'store_name', 
        'total_amount', 
        'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Define relationship with ReceiptItem
    public function receiptItems()
    {
        return $this->hasMany(\App\Models\ReceiptLog\ReceiptItem::class, 'receipt_id');
    }
}
