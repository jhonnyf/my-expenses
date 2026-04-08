<?php

use App\Http\Controllers\NfceImportController;
use Illuminate\Support\Facades\Route;

Route::match(['get', 'post'],'nfce/importar', [NfceImportController::class, 'importar'])->name('nfce.importar');
