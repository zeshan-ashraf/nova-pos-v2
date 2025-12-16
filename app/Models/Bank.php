<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Bank extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class)->withTimestamps();
    }
}
