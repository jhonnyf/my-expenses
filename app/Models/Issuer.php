<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

class Issuer extends Model
{
    use HasFactory;

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

    public function nicknames(): HasMany
    {
        return $this->hasMany(IssuerNickname::class);
    }

    public function nicknameForUser(): HasOne
    {
        return $this->hasOne(IssuerNickname::class)->where('user_id', Auth::id());
    }

    /**
     * Usa o valor de `nickname` já carregado via select/join (ex.: IssuerController)
     * ou, se ausente, o relacionamento `nicknameForUser` quando pré-carregado via with().
     * Não dispara carregamento tardio (evita N+1) — sem eager load, cai no nome oficial.
     */
    protected function nickname(): Attribute
    {
        return Attribute::get(function ($value) {
            if ($value !== null) {
                return $value;
            }

            return $this->relationLoaded('nicknameForUser') ? $this->nicknameForUser?->nickname : null;
        });
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->nickname ?: $this->name);
    }
}
