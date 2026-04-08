<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = Invoice::where('user_id', Auth::id())
            ->selectRaw('
                COALESCE(SUM(total_amount), 0) as totalExpenses,
                COALESCE(SUM(total_taxes), 0) as totalTaxes,
                COUNT(id) as totalPurchases
            ')->first();

        return view('dashboard.index', [
            'totalExpenses'  => $stats->totalExpenses,
            'totalTaxes'     => $stats->totalTaxes,
            'totalPurchases' => $stats->totalPurchases,
        ]);
    }
}
