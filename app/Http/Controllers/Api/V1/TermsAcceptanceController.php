<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TermsAcceptanceController extends Controller
{
    public function accept(Request $request): JsonResponse
    {
        $request->user()->acceptCurrentTerms();

        return response()->json(['message' => 'Termos aceitos com sucesso.']);
    }
}
