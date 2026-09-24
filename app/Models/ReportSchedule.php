<?php

namespace App\Models;

use App\Enums\ReportFrequency;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSchedule extends Model
{
    protected $fillable = ['user_id', 'frequency', 'format', 'last_sent_on'];

    protected $casts = [
        'frequency' => ReportFrequency::class,
        'last_sent_on' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Vence hoje se o dia é o do envio e ainda não foi enviado hoje. */
    public function isDueOn(Carbon $day): bool
    {
        return $this->frequency->isDueOn($day) && ! $this->last_sent_on?->isSameDay($day);
    }
}
