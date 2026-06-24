<?php

namespace App\Http\Controllers;

use App\Models\InvoiceItem;
use App\Models\ShoppingList;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecurringPurchaseController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $recurring = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
            ->where('invoices.user_id', $userId)
            ->select(
                'invoices_items.description',
                DB::raw('COUNT(DISTINCT invoices.id) as purchase_count'),
                DB::raw('COUNT(DISTINCT invoices.issuer_id) as issuer_count'),
                DB::raw('AVG(invoices_items.unit_price) as avg_price'),
                DB::raw('MIN(invoices_items.unit_price) as min_price'),
                DB::raw('MAX(invoices_items.unit_price) as max_price'),
                DB::raw('MAX(invoices.issued_at) as last_purchased_at'),
                DB::raw('MIN(invoices.issued_at) as first_purchased_at'),
                DB::raw('DATEDIFF(MAX(invoices.issued_at), MIN(invoices.issued_at)) as date_span_days')
            )
            ->groupBy('invoices_items.description')
            ->havingRaw('COUNT(DISTINCT invoices.id) >= 3')
            ->orderByDesc('purchase_count')
            ->get()
            ->map(function ($item) {
                $spanDays = max($item->date_span_days, 1);
                $item->avg_interval_days = round($spanDays / max($item->purchase_count - 1, 1));
                $item->purchases_per_month = round(($item->purchase_count / $spanDays) * 30, 1);

                return $item;
            })
            ->sortByDesc('purchases_per_month')
            ->values();

        $topDescriptions = $recurring->take(30)->pluck('description');

        $bestIssuers = collect();
        if ($topDescriptions->isNotEmpty()) {
            $bestIssuers = InvoiceItem::join('invoices', 'invoices.id', '=', 'invoices_items.invoice_id')
                ->join('issuers', 'issuers.id', '=', 'invoices.issuer_id')
                ->where('invoices.user_id', $userId)
                ->whereIn('invoices_items.description', $topDescriptions)
                ->select(
                    'invoices_items.description',
                    'issuers.id as issuer_id',
                    'issuers.name as issuer_name',
                    DB::raw('AVG(invoices_items.unit_price) as avg_price'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('MAX(invoices_items.unit) as unit')
                )
                ->groupBy('invoices_items.description', 'issuers.id', 'issuers.name')
                ->get()
                ->groupBy('description')
                ->map(fn ($group) => $group->sortBy('avg_price')->first());
        }

        $shoppingLists = ShoppingList::where('user_id', $userId)
            ->orderByDesc('updated_at')
            ->get();

        return view('recurring-purchase.index', [
            'recurring' => $recurring,
            'bestIssuers' => $bestIssuers,
            'shoppingLists' => $shoppingLists,
        ]);
    }

    public function addToShoppingList(Request $request)
    {
        $request->validate([
            'shopping_list_id' => 'required|exists:shopping_lists,id',
            'description' => 'required|string',
            'unit_price' => 'required|numeric',
            'issuer_id' => 'required|exists:issuers,id',
            'unit' => 'nullable|string',
        ]);

        $shoppingList = ShoppingList::findOrFail($request->input('shopping_list_id'));
        abort_if($shoppingList->user_id !== Auth::id(), 403);

        $item = $shoppingList->items()->create([
            'description' => $request->input('description'),
            'unit_price' => $request->input('unit_price'),
            'issuer_id' => $request->input('issuer_id'),
            'unit' => $request->input('unit'),
            'quantity' => 1,
        ]);

        return response()->json(['success' => true, 'item_id' => $item->id]);
    }
}
