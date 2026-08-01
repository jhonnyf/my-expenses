<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function index(Request $request): View
    {
        return view('dashboard.index', $this->service->getViewData(
            Auth::id(),
            $request->query('start_date'),
            $request->query('end_date'),
        ));
    }
}
