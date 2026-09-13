<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use Prunable;

    private const EXPORT_RETENTION_DAYS = 7;

    protected $fillable = [
        'collection',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function fileable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Exportações de dados pessoais (LGPD) com mais de 7 dias, removidas
     * automaticamente por `php artisan model:prune`.
     */
    public function prunable(): Builder
    {
        return static::where('collection', 'personal-data-export')
            ->where('created_at', '<', now()->subDays(self::EXPORT_RETENTION_DAYS));
    }

    protected static function booted(): void
    {
        static::deleting(function (File $file): void {
            Storage::disk($file->disk)->delete($file->path);
        });
    }
}
