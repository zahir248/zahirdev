<?php

namespace App\Models\ServiceLog;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceRecord extends Model
{
    use HasFactory;

    protected $table = 'service_records';

    protected $fillable = [
        'vehicle_id',
        'service_date',
        'service_place',
        'service_cost',
        'description',
    ];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
