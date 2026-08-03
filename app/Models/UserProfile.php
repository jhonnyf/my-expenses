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
        'latitude' => 'float',
        'longitude' => 'float',
        'location_suggestion_dismissed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
