<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Budget extends Model
{
    use HasFactory;

    public float $spent = 0.0;

    public float $percentage = 0.0;

    public float $remaining = 0.0;

    /** Gasto do mês anterior e variação vs. ele (null sem base de comparação). */
    public ?float $previous_spent = null;

    public ?float $delta_pct = null;

    /** Projeção do mês corrente (null em meses passados ou com poucos dias de dados). */
    public ?float $projected = null;

    public ?float $projected_percentage = null;

    /** Dia estimado (Y-m-d) em que o limite estoura no ritmo atual; null se não deve estourar. */
    public ?string $exceeds_on = null;

    /** Quanto ainda dá para gastar por dia até o fim do mês (só mês corrente com saldo). */
    public ?float $daily_available = null;

    protected $fillable = [
        'user_id',
        'category_id',
        'amount',
        'alerted_level',
        'alerted_month',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
