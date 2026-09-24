<?php

namespace App\Support;

use App\Notifications\BudgetThresholdReached;
use App\Notifications\FavoriteProductPriceDropped;
use Carbon\Carbon;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Traduz uma notificação de banco em algo que web e app exibem sem conhecer as classes PHP:
 * `kind` estável, `level` (info | warning | danger), `message` em texto puro e `url` da tela relacionada.
 * Notificação de tipo desconhecido (ex.: classe removida) cai num aviso genérico, sem quebrar a lista.
 */
class NotificationPresenter
{
    /**
     * @return array{kind: string, level: string, message: string, url: ?string}
     */
    public function present(DatabaseNotification $notification): array
    {
        $data = $notification->data;

        return match ($notification->type) {
            FavoriteProductPriceDropped::class => [
                'kind' => 'price_drop',
                'level' => 'info',
                'message' => sprintf(
                    '%s caiu para %s em %s (%s/%s).',
                    $data['product_name'], $this->money($data['new_price']), $data['issuer_name'], $data['city'], $data['state']
                ),
                'url' => route('prices.index', ['product' => $data['product_name']]),
            ],
            BudgetThresholdReached::class => $this->budget($data),
            default => ['kind' => 'generic', 'level' => 'info', 'message' => 'Você tem uma nova notificação.', 'url' => null],
        };
    }

    private function budget(array $data): array
    {
        $name = $data['category_name'] ?? 'Geral';
        $values = $this->money($data['spent']).' de '.$this->money($data['amount']);
        $month = Carbon::createFromFormat('Y-m', $data['month'])->locale('pt_BR')->translatedFormat('F/Y');
        $exceeded = $data['level'] >= 100;

        return [
            'kind' => $exceeded ? 'budget_exceeded' : 'budget_warning',
            'level' => $exceeded ? 'danger' : 'warning',
            'message' => $exceeded
                ? "O orçamento {$name} foi excedido em {$month} ({$values})."
                : "O orçamento {$name} chegou a {$data['level']}% do limite em {$month} ({$values}).",
            'url' => route('budgets.index', ['month' => $data['month']]),
        ];
    }

    private function money(float|int|string $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }
}
