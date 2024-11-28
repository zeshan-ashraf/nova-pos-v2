<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    use HasFactory;
    // use Sortable;
    protected $table = 'activities';

    protected $fillable = [
        'title',
        'description',
        'date',
        'images',
        'activity_cost',
        'customer_id',
    ];

    public function getImagesAttribute($value)
    {
        return json_decode($value, true);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

