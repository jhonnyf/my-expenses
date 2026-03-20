<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'user_id',
        'access_key',
        'number',
        'series',
        'issued_at',
        'environment',
        'issuer_cnpj',
        'issuer_name',
        'issuer_street',
        'issuer_street_number',
        'issuer_neighborhood',
        'issuer_city',
        'issuer_state',
        'issuer_zip_code',
        'total_icms_base',
        'total_icms',
        'total_products',
        'total_amount',
        'total_taxes',
    ];

    protected $casts = [
        'issued_at'       => 'datetime',
        'total_icms_base' => 'decimal:2',
        'total_icms'      => 'decimal:2',
        'total_products'  => 'decimal:2',
        'total_amount'    => 'decimal:2',
        'total_taxes'     => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }
}
