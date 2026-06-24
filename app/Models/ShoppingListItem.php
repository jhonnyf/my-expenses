<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShoppingListItem extends Model
{
    protected $fillable = [
        'shopping_list_id',
        'issuer_id',
        'description',
        'unit',
        'unit_price',
        'quantity',
        'purchased_at',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'purchased_at' => 'datetime',
    ];

    public function shoppingList(): BelongsTo
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Issuer::class);
    }
}
