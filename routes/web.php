<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\IssuerController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MyPurchaseController;
use App\Http\Controllers\PriceHistoryController;
use App\Http\Controllers\RecurringPurchaseController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\SocialAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard.index');
});

Route::group(['prefix' => 'forgot-password', 'as' => 'password.'], function () {
    Route::get('/', [ForgotPasswordController::class, 'index'])->name('request');
    Route::post('/', [ForgotPasswordController::class, 'send'])->name('email')->middleware('throttle:5,1');
});

Route::get('/reset-password', [ResetPasswordController::class, 'index'])->name('password.reset');
Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update')->middleware('throttle:5,1');

Route::group(['prefix' => 'register', 'as' => 'register.'], function () {
    Route::get('/', [RegisterController::class, 'index'])->name('index');
    Route::post('/', [RegisterController::class, 'store'])->name('store')->middleware('throttle:5,1');
});

Route::group(['prefix' => 'login', 'as' => 'login.'], function () {
    Route::get('/', [LoginController::class, 'index'])->name('index');
    Route::post('execute', [LoginController::class, 'execute'])->name('execute')->middleware('throttle:5,1');
    Route::post('logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('social/{provider}', [SocialAuthController::class, 'redirect'])->name('social.redirect')->middleware('throttle:10,1');
    Route::get('social/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback')->middleware('throttle:10,1');
});

Route::group(['middleware' => 'auth'], function () {

    Route::group(['prefix' => 'dashboard', 'as' => 'dashboard.'], function () {
        Route::get('/', [DashboardController::class, 'index'])->name('index');
    });

    Route::group(['prefix' => 'issuers', 'as' => 'issuers.'], function () {
        Route::get('/', [IssuerController::class, 'index'])->name('index');
        Route::get('detail/{id?}', [IssuerController::class, 'detail'])->name('detail');
        Route::post('{id}/favorite', [IssuerController::class, 'toggleFavorite'])->name('favorite');
        Route::put('{id}/nickname', [IssuerController::class, 'updateNickname'])->name('nickname.update');
    });

    Route::group(['prefix' => 'my-purchases', 'as' => 'my-purchases.'], function () {
        Route::get('/', [MyPurchaseController::class, 'index'])->name('index');
        Route::get('detail/{invoice}', [MyPurchaseController::class, 'detail'])->name('detail');
        Route::get('upload', [MyPurchaseController::class, 'uploadForm'])->name('upload.form');
        Route::post('upload', [MyPurchaseController::class, 'upload'])->name('upload');
        Route::post('import-qrcode', [MyPurchaseController::class, 'importByQrCode'])->name('import-qrcode');
        Route::post('import-by-key', [MyPurchaseController::class, 'importByAccessKey'])->name('import-by-key');
    });

    Route::group(['prefix' => 'categories', 'as' => 'categories.'], function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::patch('{category}', [CategoryController::class, 'update'])->name('update');
        Route::delete('{category}', [CategoryController::class, 'destroy'])->name('destroy');
        Route::post('assign-item', [CategoryController::class, 'assignItem'])->name('assign-item');
        Route::post('auto-categorize', [CategoryController::class, 'autoCategorize'])->name('auto-categorize');
    });

    Route::group(['prefix' => 'price-history', 'as' => 'price-history.'], function () {
        Route::get('/', [PriceHistoryController::class, 'index'])->name('index');
        Route::get('search', [PriceHistoryController::class, 'search'])->name('search');
        Route::get('show', [PriceHistoryController::class, 'show'])->name('show');
    });

    Route::get('search', [SearchController::class, 'search'])->name('search');

    Route::group(['prefix' => 'budgets', 'as' => 'budgets.'], function () {
        Route::get('/', [BudgetController::class, 'index'])->name('index');
        Route::post('/', [BudgetController::class, 'store'])->name('store');
        Route::delete('{budget}', [BudgetController::class, 'destroy'])->name('destroy');
    });

    Route::group(['prefix' => 'reports', 'as' => 'reports.'], function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::post('generate', [ReportController::class, 'generate'])->name('generate');
        Route::post('pdf', [ReportController::class, 'exportPdf'])->name('pdf');
        Route::post('csv', [ReportController::class, 'exportCsv'])->name('csv');
    });

    Route::group(['prefix' => 'recurring-purchases', 'as' => 'recurring-purchases.'], function () {
        Route::get('/', [RecurringPurchaseController::class, 'index'])->name('index');
        Route::post('add-to-list', [RecurringPurchaseController::class, 'addToShoppingList'])->name('add-to-list');
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

    Route::group(['prefix' => 'account', 'as' => 'account.'], function () {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::patch('/', [AccountController::class, 'update'])->name('update');
        Route::patch('password', [AccountController::class, 'updatePassword'])->name('password');
        Route::post('avatar', [AccountController::class, 'updateAvatar'])->name('avatar');
    });

});
