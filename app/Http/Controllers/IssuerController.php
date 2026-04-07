<?php

namespace App\Http\Controllers;

use App\Models\Issuer;

class IssuerController extends Controller
{

    public function index()
    {
        $issuers = Issuer::orderBy('name')->paginate(15);

        $data = [
            'records' => $issuers,
        ];

        return view('issuer.index', $data);
    }

    public function detail($id)
    {
        $issuer = Issuer::find($id);

        $data = [
            'record' => $issuer,
        ];

        return view('issuer.detail', $data);
    }
}
