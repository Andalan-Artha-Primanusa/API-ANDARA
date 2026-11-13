<?php

namespace App\Modules\Asset\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Asset extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'brand',
        'model',
        'serial_number',
        'condition',
        'status',
        'purchase_date',
        'purchase_price',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_price' => 'decimal:2',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }
}
