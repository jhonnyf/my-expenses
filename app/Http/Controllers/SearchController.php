<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Issuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SearchController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $userId = Auth::id();

        $issuers = Issuer::whereHas('invoices', fn ($q) => $q->where('user_id', $userId))
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('cnpj', 'like', "%{$query}%");
            })
            ->select('id', 'name', 'cnpj', 'city', 'state')
            ->limit(5)
            ->get()
            ->map(fn ($i) => [
                'type' => 'issuer',
                'id' => $i->id,
                'title' => $i->name,
                'subtitle' => $i->cnpj.' - '.$i->city.'/'.$i->state,
                'url' => route('issuers.detail', $i->id),
            ]);

        $invoices = Invoice::where('user_id', $userId)
            ->with('issuer:id,name')
            ->where(function ($q) use ($query) {
                $q->where('number', 'like', "%{$query}%")
                    ->orWhere('access_key', 'like', "%{$query}%");
            })
            ->select('id', 'number', 'series', 'issuer_id', 'issued_at', 'total_amount')
            ->orderByDesc('issued_at')
            ->limit(5)
            ->get()
            ->map(fn ($i) => [
                'type' => 'invoice',
                'id' => $i->id,
                'title' => "NFC-e #{$i->number}/{$i->series}",
                'subtitle' => ($i->issuer->name ?? '').' - R$ '.number_format($i->total_amount, 2, ',', '.'),
                'url' => route('my-purchases.detail', $i->id),
            ]);

        $products = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->where('invoices_items.description', 'like', "%{$query}%")
            ->select(
                'invoices_items.description',
                DB::raw('COUNT(*) as count'),
                DB::raw('AVG(invoices_items.unit_price) as avg_price')
            )
            ->groupBy('invoices_items.description')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'type' => 'product',
                'title' => $p->description,
                'subtitle' => $p->count.'x comprado - Média R$ '.number_format($p->avg_price, 2, ',', '.'),
                'url' => route('price-history.index').'?q='.urlencode($p->description),
            ]);

        return response()->json([
            'emissores' => $issuers,
            'notas_fiscais' => $invoices,
            'produtos' => $products,
        ]);
    }
}
