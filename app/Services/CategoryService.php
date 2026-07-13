<?php

namespace App\Services;

use App\Models\Category;
use App\Models\InvoiceItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CategoryService
{
    public function getCategoriesWithSpending(int $userId): Collection
    {
        $categories = Category::forUser($userId)
            ->withCount(['items' => fn ($q) => $q->whereHas('invoice', fn ($q2) => $q2->where('user_id', $userId))])
            ->get();

        $spendingByCategory = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->whereNotNull('invoices_items.category_id')
            ->select('invoices_items.category_id', DB::raw('SUM(invoices_items.total_price) as total'))
            ->groupBy('invoices_items.category_id')
            ->pluck('total', 'category_id');

        return $categories->map(function (Category $cat) use ($spendingByCategory) {
            $cat->total_spent = (float) ($spendingByCategory[$cat->id] ?? 0);

            return $cat;
        })->sortByDesc('total_spent')->values();
    }

    public function countUncategorizedItems(int $userId): int
    {
        return InvoiceItem::whereNull('category_id')
            ->whereHas('invoice', fn ($q) => $q->where('user_id', $userId))
            ->count();
    }

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
