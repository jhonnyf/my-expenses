<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\InvoiceResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service) {}

    public function index(Request $request): JsonResponse
    {
        $data = $this->service->getViewData($request->user()->id);

        if ($data['lastPurchase']) {
            $data['lastPurchase'] = new InvoiceResource($data['lastPurchase']);
        }

        return $this->success($data);
    }
}
