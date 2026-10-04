<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'client_code',
        'sku',
        'uom',
        'category',
        'unit_per_pack',
        'weight_kg',
    ];

    protected function casts(): array
    {
        return [
            'unit_per_pack' => 'decimal:4',
            'weight_kg' => 'decimal:4',
        ];
    }
}