<?php

namespace App\Events;

use App\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;

class InvoiceImported
{
    use Dispatchable;

    public function __construct(public readonly Invoice $invoice) {}
}
