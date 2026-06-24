<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issuer extends Model
{
    protected $fillable = [
        'cnpj',
        'name',
        'street',
        'street_number',
        'neighborhood',
        'city',
        'state',
        'zip_code',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function favoritedByUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorite_issuers')->withTimestamps();
    }
}
