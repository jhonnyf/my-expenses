<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    public float $total_spent = 0.0;

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'icon',
        'keywords',
    ];

    protected $casts = [
        'keywords' => 'array',
    ];

    /**
     * A FK do orçamento é nullOnDelete: sem isto, apagar a categoria transformaria o orçamento dela num
     * segundo orçamento "Geral" (o índice único aceita vários nulos).
     */
    protected static function booted(): void
    {
        static::deleting(fn (Category $category) => Budget::where('category_id', $category->id)->delete());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where(function ($q) use ($userId) {
            $q->whereNull('user_id')->orWhere('user_id', $userId);
        });
    }
}
