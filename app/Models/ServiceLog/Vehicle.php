<?php

namespace App\Models\ServiceLog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    // The table associated with the model (optional if the name follows the plural convention)
    protected $table = 'vehicles';

    // The attributes that are mass assignable
    protected $fillable = [
        'user_id',
        'model',
        'year',
        'registration_number',
    ];

    // Define the relationship with the User model (foreign key reference)
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
