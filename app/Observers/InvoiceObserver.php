<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\DashboardService;

class InvoiceObserver
{
    public function saved(Invoice $invoice): void
    {
        DashboardService::flushCache($invoice->user_id);
    }

    public function deleted(Invoice $invoice): void
    {
        DashboardService::flushCache($invoice->user_id);
    }
}
