<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_name',
        'price',
        'shots_count',
        'shot_price',
        'quantity',
        'subtotal',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'shots_count' => 'integer',
        'shot_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the formatted display name including shots if present.
     */
    public function getFormattedNameAttribute(): string
    {
        if ($this->shots_count > 0) {
            return $this->product_name.' (+'.$this->shots_count.' Shot)';
        }

        return $this->product_name;
    }
}
