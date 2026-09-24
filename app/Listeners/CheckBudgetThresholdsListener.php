<?php

namespace App\Listeners;

use App\Actions\NotifyBudgetThresholdsAction;
use App\Events\InvoiceImported;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;

class CheckBudgetThresholdsListener implements ShouldQueue
{
    public function __construct(private readonly NotifyBudgetThresholdsAction $action) {}

    public function handle(InvoiceImported $event): void
    {
        $user = User::find($event->invoice->user_id);

        if ($user !== null) {
            $this->action->execute($user);
        }
    }
}
