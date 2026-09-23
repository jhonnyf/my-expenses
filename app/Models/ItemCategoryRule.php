<?php

namespace App\Models;

use App\Support\ProductNameNormalizer;
use Illuminate\Database\Eloquent\Model;

class ItemCategoryRule extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_AI = 'ai';

    private const KEY_MAX_LENGTH = 191;

    protected $fillable = ['user_id', 'description_key', 'category_id', 'source'];

    public static function keyFor(string $description): string
    {
        return mb_substr(ProductNameNormalizer::normalize($description), 0, self::KEY_MAX_LENGTH);
    }
}
