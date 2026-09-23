<?php

namespace App\Actions;

use App\Exceptions\AiSuggestionUnavailableException;
use App\Models\Category;
use App\Models\InvoiceItem;
use App\Models\ItemCategoryRule;
use App\Services\ItemCategoryAiClassifierService;

class SuggestItemCategoryAction
{
    public function __construct(private readonly ItemCategoryAiClassifierService $classifier) {}

    /**
     * @return int|null id da categoria sugerida; `null` quando a IA não tem confiança suficiente
     *
     * @throws AiSuggestionUnavailableException quando o Gemini não está configurado ou falha
     */
    public function execute(InvoiceItem $item): ?int
    {
        $categories = Category::forUser($item->invoice->user_id)->pluck('name', 'id')->all();
        $classified = $this->classifier->isConfigured()
            ? $this->classifier->classify([ItemCategoryRule::keyFor($item->description)], $categories)
            : null;

        if ($classified === null) {
            throw new AiSuggestionUnavailableException;
        }

        return $classified[0] ?? null;
    }
}
