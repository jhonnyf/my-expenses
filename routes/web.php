<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssuerController;
use App\Http\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard.index');
});

Route::group(['prefix' => 'login', 'as' => 'login.'], function () {
    Route::get('/', [LoginController::class, 'index'])->name('index');
    Route::post('execute', [LoginController::class, 'execute'])->name('execute');
    Route::get('logout', [LoginController::class, 'logout'])->name('logout');
});

Route::group(['middleware' => 'auth'], function () {

    Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.'], function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
    });

    Route::group(['prefix' => 'issuers', 'as' => 'issuers.'], function () {
        Route::get('/', [IssuerController::class, 'index'])->name('index');
        Route::get('detail/{id?}', [IssuerController::class, 'detail'])->name('detail');
    });

});
