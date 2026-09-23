<?php

namespace App\Listeners;

use App\Events\InvoiceImported;
use App\Jobs\AiCategorizeItemsJob;
use App\Services\CategoryService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AutoCategorizeListener implements ShouldQueue
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public function handle(InvoiceImported $event): void
    {
        $userId = $event->invoice->user_id;

        $this->categoryService->autoCategorize($userId);
        AiCategorizeItemsJob::dispatch($userId);
    }
}
