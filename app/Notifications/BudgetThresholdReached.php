<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Aviso de que um orçamento mensal chegou a 80% ou estourou (100%).
 */
class BudgetThresholdReached extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ?string $categoryName,
        private readonly int $level,
        private readonly float $spent,
        private readonly float $amount,
        private readonly string $month,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category_name' => $this->categoryName,
            'level' => $this->level,
            'spent' => $this->spent,
            'amount' => $this->amount,
            'month' => $this->month,
        ];
    }
}
