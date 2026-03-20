<?php

use App\Http\Controllers\NFController;
use App\Http\Controllers\NfceImportController;
use Illuminate\Support\Facades\Route;

Route::post('nf/consultar', [NFController::class, 'consultar'])->name('nf.consultar');
Route::post('nfce/importar', [NfceImportController::class, 'importar'])->name('nfce.importar');
