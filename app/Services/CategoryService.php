<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InvoiceItem;

class CategoryService
{
    public function autoCategorize(int $userId): int
    {
        $categories = Category::forUser($userId)
            ->whereNotNull('keywords')
            ->orderByRaw("CASE WHEN name = 'Outros' THEN 1 ELSE 0 END")
            ->get();

        if ($categories->isEmpty()) {
            return 0;
        }

        $totalCategorized = 0;
        $updates = [];

        InvoiceItem::whereNull('category_id')
            ->whereHas('invoice', fn ($q) => $q->where('user_id', $userId))
            ->select('id', 'description')
            ->chunkById(500, function ($items) use ($categories, &$updates, &$totalCategorized) {
                foreach ($items as $item) {
                    $desc = mb_strtoupper($item->description);
                    foreach ($categories as $category) {
                        foreach ($category->keywords ?? [] as $keyword) {
                            if (str_contains($desc, mb_strtoupper($keyword))) {
                                $updates[$category->id][] = $item->id;
                                $totalCategorized++;
                                break 2;
                            }
                        }
                    }
                }
            });

        foreach ($updates as $categoryId => $itemIds) {
            foreach (array_chunk($itemIds, 500) as $chunk) {
                InvoiceItem::whereIn('id', $chunk)->update(['category_id' => $categoryId]);
            }
        }

        return $totalCategorized;
    }
}
