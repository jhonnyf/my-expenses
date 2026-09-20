<?php

namespace App\Jobs;

use App\Models\Budget;
use App\Models\Category;
use App\Models\FavoriteProduct;
use App\Models\ShoppingList;
use App\Models\User;
use App\Notifications\PersonalDataExportReady;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExportPersonalDataJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $userId) {}

    public function handle(): void
    {
        $user = User::with([
            'profile',
            'subscription',
            'invoices' => fn ($query) => $query->includingUnauthorized()->with(['issuer', 'items', 'payments']),
            'favoriteIssuers',
        ])->find($this->userId);

        if (! $user) {
            return;
        }

        $path = "exports/{$user->id}/".Str::uuid().'.json';

        Storage::disk('local')->put($path, json_encode(
            $this->buildExport($user),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));

        $file = $user->files()->create([
            'collection' => 'personal-data-export',
            'disk' => 'local',
            'path' => $path,
            'original_name' => 'meus-dados.json',
            'mime_type' => 'application/json',
            'size' => Storage::disk('local')->size($path),
        ]);

        $user->notify(new PersonalDataExportReady($file));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExport(User $user): array
    {
        return [
            'gerado_em' => now()->toIso8601String(),
            'cadastro' => [
                'nome' => $user->name,
                'email' => $user->email,
                'cpf' => $user->profile?->cpf,
                'cnpj' => $user->profile?->cnpj,
                'cidade' => $user->profile?->cidade,
                'estado' => $user->profile?->estado,
                'termos_aceitos_em' => $user->terms_accepted_at?->toIso8601String(),
                'membro_desde' => $user->created_at->toIso8601String(),
            ],
            'assinatura' => $user->subscription ? [
                'plano' => $user->subscription->plan->value,
                'status' => $user->subscription->status->value,
                'iniciada_em' => $user->subscription->started_at?->toIso8601String(),
                'expira_em' => $user->subscription->expires_at?->toIso8601String(),
            ] : null,
            'notas_fiscais' => $user->invoices->map(fn ($invoice) => [
                'numero' => $invoice->number,
                'serie' => $invoice->series,
                'status' => $invoice->status->value,
                'emitida_em' => $invoice->issued_at?->toIso8601String(),
                'emitente' => $invoice->issuer?->name,
                'total' => $invoice->total_amount,
                'itens' => $invoice->items->map(fn ($item) => [
                    'descricao' => $item->description,
                    'quantidade' => $item->quantity,
                    'valor_unitario' => $item->unit_price,
                    'valor_total' => $item->total_price,
                ])->all(),
                'pagamentos' => $invoice->payments->map(fn ($payment) => [
                    'forma' => $payment->method,
                    'valor' => $payment->amount,
                ])->all(),
            ])->all(),
            'categorias' => Category::where('user_id', $user->id)->pluck('name')->all(),
            'orcamentos' => Budget::where('user_id', $user->id)->with('category')->get()
                ->map(fn ($budget) => [
                    'categoria' => $budget->category?->name,
                    'valor' => $budget->amount,
                ])->all(),
            'listas_de_compras' => ShoppingList::where('user_id', $user->id)->with('items')->get()
                ->map(fn ($list) => [
                    'nome' => $list->name,
                    'itens' => $list->items->pluck('description')->all(),
                ])->all(),
            'produtos_favoritos' => FavoriteProduct::where('user_id', $user->id)->pluck('canonical_name')->all(),
            'emitentes_favoritos' => $user->favoriteIssuers->pluck('name')->all(),
        ];
    }
}
