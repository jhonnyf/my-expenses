<?php

namespace App\Http\Controllers;

use App\Models\Invoice;

class MyPurchaseController extends Controller
{
    public function index()
    {

        $record = Invoice::all();

        return $record;

        $data = [];

        return view('my-purchase.index', $data);
    }
}
