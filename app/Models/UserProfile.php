<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    use HasFactory;

    protected $table = 'users_profiles';

    protected $fillable = [
        'user_id',
        'cpf',
        'cnpj',
        'cidade',
        'estado',
        'latitude',
        'longitude',
        'location_suggestion_dismissed_at',
    ];

    protected $casts = [
        'cpf' => 'encrypted',
        'cnpj' => 'encrypted',
        'latitude' => 'float',
        'longitude' => 'float',
        'location_suggestion_dismissed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** CPF para exibição: só os 3 primeiros e os 2 últimos dígitos. */
    public function maskedCpf(): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $this->cpf);

        if (strlen($digits) !== 11) {
            return null;
        }

        return substr($digits, 0, 3).'.***.***-'.substr($digits, 9);
    }
}
