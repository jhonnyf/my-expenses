<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    private const AUTHORIZED_SCOPE = 'authorized';

    protected $fillable = [
        'user_id',
        'access_key',
        'number',
        'series',
        'issued_at',
        'environment',
        'status',
        'issuer_id',
        'total_icms_base',
        'total_icms',
        'total_products',
        'total_discount',
        'total_amount',
        'total_taxes',
        'raw_xml',
        'qrcode_url',
    ];

    protected $attributes = [
        'status' => 'authorized',
    ];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'issued_at' => 'datetime',
        'total_icms_base' => 'decimal:2',
        'total_icms' => 'decimal:2',
        'total_products' => 'decimal:2',
        'total_discount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'total_taxes' => 'decimal:2',
    ];

    /**
     * Por padrão só notas autorizadas contam (totais, orçamentos, relatórios, preços).
     * Quem precisa ver as demais (lista, detalhe, exportação de dados) usa includingUnauthorized().
     *
     * Não cobre DB::table('invoices') cru. Joins a partir de InvoiceItem/InvoicePayment são seguros sem
     * filtro: só nota autorizada tem itens e pagamentos (a promoção grava itens e status na mesma transação).
     */
    protected static function booted(): void
    {
        static::addGlobalScope(
            self::AUTHORIZED_SCOPE,
            fn (Builder $query) => $query->where('invoices.status', InvoiceStatus::Authorized)
        );
    }

    public function scopeIncludingUnauthorized(Builder $query): Builder
    {
        return $query->withoutGlobalScope(self::AUTHORIZED_SCOPE);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->withoutGlobalScope(self::AUTHORIZED_SCOPE)
            ->where('invoices.status', InvoiceStatus::Pending);
    }

    /** O detalhe da nota precisa abrir também para notas pendentes/não confirmadas. */
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        return $this->newQuery()
            ->includingUnauthorized()
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->first();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(Issuer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function qrCodeReads(): HasMany
    {
        return $this->hasMany(QrCodeRead::class);
    }
}
