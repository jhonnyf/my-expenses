<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\IssuerController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MyPurchaseController;
use App\Http\Controllers\ShoppingListController;
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
        Route::post('{id}/favorite', [IssuerController::class, 'toggleFavorite'])->name('favorite');
    });

    Route::group(['prefix' => 'my-purchases', 'as' => 'my-purchases.'], function () {
        Route::get('/', [MyPurchaseController::class, 'index'])->name('index');
        Route::get('detail/{invoice}', [MyPurchaseController::class, 'detail'])->name('detail');
        Route::get('upload', [MyPurchaseController::class, 'uploadForm'])->name('upload.form');
        Route::post('upload', [MyPurchaseController::class, 'upload'])->name('upload');
    });

    Route::group(['prefix' => 'shopping-list', 'as' => 'shopping-list.'], function () {
        Route::get('/', [ShoppingListController::class, 'index'])->name('index');
        Route::get('search', [ShoppingListController::class, 'search'])->name('search');
        Route::post('/', [ShoppingListController::class, 'store'])->name('store');
        Route::get('{shoppingList}', [ShoppingListController::class, 'show'])->name('show');
        Route::patch('{shoppingList}', [ShoppingListController::class, 'update'])->name('update');
        Route::delete('{shoppingList}', [ShoppingListController::class, 'destroy'])->name('destroy');
        Route::post('{shoppingList}/items', [ShoppingListController::class, 'addItem'])->name('items.add');
        Route::patch('{shoppingList}/items/{item}', [ShoppingListController::class, 'updateItem'])->name('items.update');
        Route::delete('{shoppingList}/items/{item}', [ShoppingListController::class, 'removeItem'])->name('items.remove');
        Route::post('{shoppingList}/items/{item}/toggle-purchased', [ShoppingListController::class, 'togglePurchased'])->name('items.toggle-purchased');
    });

});
