<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FavoriteProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'canonical_name',
        'unit',
        'last_notified_price',
        'last_notified_at',
    ];

    protected $casts = [
        'last_notified_price' => 'float',
        'last_notified_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
