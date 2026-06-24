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

        $uncategorized = InvoiceItem::whereNull('category_id')
            ->whereHas('invoice', fn ($q) => $q->where('user_id', $userId))
            ->get();

        $totalCategorized = 0;
        $updates = [];

        foreach ($uncategorized as $item) {
            $desc = mb_strtoupper($item->description);
            foreach ($categories as $category) {
                $keywords = $category->keywords ?? [];
                foreach ($keywords as $keyword) {
                    if (str_contains($desc, mb_strtoupper($keyword))) {
                        $updates[$category->id][] = $item->id;
                        $totalCategorized++;
                        break 2;
                    }
                }
            }
        }

        foreach ($updates as $categoryId => $itemIds) {
            InvoiceItem::whereIn('id', $itemIds)->update(['category_id' => $categoryId]);
        }

        return $totalCategorized;
    }
}
