<?php

namespace App\Listeners;

use App\Events\InvoiceImported;
use App\Services\CategoryService;
use Illuminate\Contracts\Queue\ShouldQueue;

class AutoCategorizeListener implements ShouldQueue
{
    public function __construct(private readonly CategoryService $categoryService) {}

    public function handle(InvoiceImported $event): void
    {
        $this->categoryService->autoCategorize($event->invoice->user_id);
    }
}
