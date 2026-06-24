<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PriceHistoryController extends Controller
{
    public function index()
    {
        return view('price-history.index');
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');

        if (strlen($query) < 2) {
            return response()->json([]);
        }

        $items = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', Auth::id())
            ->where('invoices_items.description', 'like', "%{$query}%")
            ->select(
                'invoices_items.description',
                DB::raw('COUNT(*) as purchase_count'),
                DB::raw('MIN(invoices_items.unit_price) as min_price'),
                DB::raw('MAX(invoices_items.unit_price) as max_price'),
                DB::raw('AVG(invoices_items.unit_price) as avg_price'),
                DB::raw('MAX(invoices.issued_at) as last_purchased_at')
            )
            ->groupBy('invoices_items.description')
            ->orderByDesc('purchase_count')
            ->limit(20)
            ->get();

        return response()->json($items);
    }

    public function show(Request $request)
    {
        $description = $request->input('description', '');

        if (empty($description)) {
            return response()->json([]);
        }

        $userId = Auth::id();

        $timeline = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
            ->where('invoices.user_id', $userId)
            ->where('invoices_items.description', $description)
            ->select(
                'invoices_items.unit_price',
                'invoices_items.quantity',
                'invoices_items.unit',
                'invoices.issued_at',
                'issuers.name as issuer_name',
                'issuers.id as issuer_id'
            )
            ->orderBy('invoices.issued_at')
            ->get();

        $prices = $timeline->pluck('unit_price')->map(fn ($p) => (float) $p);
        $minPrice = $prices->min() ?? 0;
        $maxPrice = $prices->max() ?? 0;
        $avgPrice = $prices->avg() ?? 0;
        $variationPct = $minPrice > 0 ? (($maxPrice - $minPrice) / $minPrice) * 100 : 0;

        return response()->json([
            'timeline' => $timeline,
            'summary' => [
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'avg_price' => round($avgPrice, 2),
                'variation_pct' => round($variationPct, 1),
            ],
        ]);
    }
}
