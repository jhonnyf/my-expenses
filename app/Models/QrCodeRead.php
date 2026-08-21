<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrCodeRead extends Model
{
    use HasFactory;

    protected $table = 'qrcode_reads';

    protected $fillable = [
        'user_id',
        'qrcode_url',
        'status',
        'error_message',
        'invoice_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
