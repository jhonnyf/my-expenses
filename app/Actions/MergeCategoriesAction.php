<?php

namespace App\Actions;

use App\Models\Budget;
use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\ItemCategoryRule;
use Illuminate\Support\Facades\DB;

class MergeCategoriesAction
{
    /**
     * Move os itens e as regras aprendidas de `$source` (categoria do usuário) para `$target` e apaga `$source`.
     * O orçamento da origem vai junto se o destino não tiver um; senão é descartado (a FK o converteria em
     * orçamento geral). Se o destino também for do usuário, herda as palavras-chave da origem.
     *
     * @return int quantidade de itens movidos
     */
    public function execute(Category $source, Category $target, int $userId): int
    {
        return DB::transaction(function () use ($source, $target, $userId) {
            $moved = InvoiceItem::where('category_id', $source->id)
                ->whereIn('invoice_id', DB::table('invoices')->where('user_id', $userId)->select('id'))
                ->update(['category_id' => $target->id]);

            ItemCategoryRule::where('user_id', $userId)
                ->where('category_id', $source->id)
                ->update(['category_id' => $target->id]);

            $sourceBudget = Budget::where('user_id', $userId)->where('category_id', $source->id)->first();
            if ($sourceBudget !== null) {
                Budget::where('user_id', $userId)->where('category_id', $target->id)->exists()
                    ? $sourceBudget->delete()
                    : $sourceBudget->update(['category_id' => $target->id]);
            }

            if ($target->user_id === $userId) {
                $target->update(['keywords' => collect([...($target->keywords ?? []), ...($source->keywords ?? [])])
                    ->unique(fn (string $keyword) => mb_strtoupper($keyword))
                    ->values()
                    ->all()]);
            }

            $source->delete();

            return $moved;
        });
    }
}
