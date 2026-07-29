<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductAliasSuggestionDismissal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'description_a',
        'description_b',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
