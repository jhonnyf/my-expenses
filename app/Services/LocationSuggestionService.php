<?php

namespace App\Services;

use App\Models\User;

/**
 * Detecta usuários comprando com frequência fora da cidade/estado cadastrados
 * no perfil e sugere atualizar o cadastro — baseado nas notas fiscais recentes,
 * não em dado de geolocalização (não depende de geocoding).
 */
class LocationSuggestionService
{
    private const LOOKBACK_DAYS = 90;

    private const MIN_FREQUENCY = 0.6;

    private const DISMISS_COOLDOWN_DAYS = 30;

    /**
     * @return array{city: string, state: string}|null
     */
    public function suggestionFor(User $user): ?array
    {
        $profile = $user->profile;

        if ($this->wasRecentlyDismissed($profile)) {
            return null;
        }

        $invoices = $user->invoices()
            ->with('issuer:id,city,state')
            ->where('issued_at', '>=', now()->subDays(self::LOOKBACK_DAYS))
            ->get()
            ->filter(fn ($invoice) => ! empty($invoice->issuer?->city) && ! empty($invoice->issuer?->state));

        if ($invoices->isEmpty()) {
            return null;
        }

        $grouped = $invoices->groupBy(fn ($invoice) => $invoice->issuer->city.'|'.$invoice->issuer->state);
        $topKey = $grouped->sortByDesc(fn ($group) => $group->count())->keys()->first();
        $topCount = $grouped->get($topKey)->count();

        if (($topCount / $invoices->count()) < self::MIN_FREQUENCY) {
            return null;
        }

        [$city, $state] = explode('|', $topKey);

        if ($profile?->cidade === $city && $profile?->estado === $state) {
            return null;
        }

        return ['city' => $city, 'state' => $state];
    }

    private function wasRecentlyDismissed(?object $profile): bool
    {
        $dismissedAt = $profile?->location_suggestion_dismissed_at;

        return $dismissedAt !== null && $dismissedAt->gt(now()->subDays(self::DISMISS_COOLDOWN_DAYS));
    }
}
