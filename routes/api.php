<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BudgetController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\FavoriteProductController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\IssuerController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PasswordResetController;
use App\Http\Controllers\Api\V1\PriceComparisonController;
use App\Http\Controllers\Api\V1\PriceHistoryController;
use App\Http\Controllers\Api\V1\ProductAliasController;
use App\Http\Controllers\Api\V1\RecurringPurchaseController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\ShoppingListController;
use App\Http\Controllers\Api\V1\SocialAuthController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TermsAcceptanceController;
use App\Http\Controllers\MyPurchaseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('nfce/upload', [MyPurchaseController::class, 'upload'])->name('nfce.upload');
});

// ─── API v1 ──────────────────────────────────────────────────────────────────
Route::prefix('v1')->name('api.v1.')->group(function () {

    // Públicas: autenticação (limitado a 10 req/min por IP)
    Route::middleware('throttle:api-auth')->prefix('auth')->name('auth.')->group(function () {
        Route::post('login', [AuthController::class,       'login'])->name('login');
        Route::post('register', [AuthController::class,       'register'])->name('register');
        Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword'])->name('forgot-password');
        Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->name('reset-password');
        Route::post('social/{provider}', [SocialAuthController::class, 'login'])->name('social.login');
    });

    // Protegidas: todas requerem token Sanctum (limitado a 60 req/min por usuário)
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {

        // Auth
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::post('email/resend', [AuthController::class, 'resendVerificationEmail'])->name('email.resend');
        });

        Route::post('terms/accept', [TermsAcceptanceController::class, 'accept'])->name('terms.accept');

        // Demais rotas exigem e-mail verificado e Termos/Política na versão vigente
        Route::middleware(['verified', 'terms.accepted'])->group(function () {

            // Dashboard
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

            // Busca global
            Route::get('search', [SearchController::class, 'search'])->name('search');

            // Notas fiscais
            Route::prefix('invoices')->name('invoices.')->group(function () {
                Route::get('/', [InvoiceController::class, 'index'])->name('index');
                Route::get('{invoice}', [InvoiceController::class, 'show'])->name('show');
                Route::delete('{invoice}', [InvoiceController::class, 'destroy'])->name('destroy');
                Route::post('import/xml', [InvoiceController::class, 'importXml'])->name('import.xml');
                Route::post('import/qrcode', [InvoiceController::class, 'importByQrCode'])->name('import.qrcode');
                Route::post('import/key', [InvoiceController::class, 'importByKey'])->name('import.key');
            });

            // Emitentes
            Route::prefix('issuers')->name('issuers.')->group(function () {
                Route::get('/', [IssuerController::class, 'index'])->name('index');
                Route::get('{id}', [IssuerController::class, 'show'])->name('show');
                Route::post('{id}/favorite', [IssuerController::class, 'toggleFavorite'])->name('favorite');
                Route::put('{id}/nickname', [IssuerController::class, 'updateNickname'])->name('nickname.update');
            });

            // Categorias — rotas fixas ANTES do apiResource
            Route::post('categories/assign-item', [CategoryController::class, 'assignItem'])->name('categories.assign-item');
            Route::post('categories/auto-categorize', [CategoryController::class, 'autoCategorize'])->name('categories.auto-categorize');
            Route::post('categories/revert-auto-categorization', [CategoryController::class, 'revertAutoCategorization'])->name('categories.revert-auto-categorization');
            Route::post('categories/preview-keywords', [CategoryController::class, 'previewKeywords'])->name('categories.preview-keywords');
            Route::get('categories/uncategorized', [CategoryController::class, 'uncategorized'])->name('categories.uncategorized');
            Route::post('categories/{category}/merge', [CategoryController::class, 'merge'])->name('categories.merge');
            Route::post('categories/suggest-item-category', [CategoryController::class, 'suggestItemCategory'])
                ->name('categories.suggest-item-category')
                ->middleware(['throttle:ai-suggestions', 'pro:ai_suggestions']);
            Route::post('categories/suggest-keywords', [CategoryController::class, 'suggestKeywords'])
                ->name('categories.suggest-keywords')
                ->middleware(['throttle:ai-suggestions', 'pro:ai_suggestions']);
            Route::apiResource('categories', CategoryController::class);

            // Orçamentos
            Route::apiResource('budgets', BudgetController::class)->only(['index', 'store', 'destroy']);

            // Relatórios
            Route::prefix('reports')->name('reports.')->group(function () {
                Route::get('/', [ReportController::class, 'generate'])->name('generate');
                Route::get('csv', [ReportController::class, 'exportCsv'])->name('csv')->middleware('pro:report_csv');
                Route::post('email', [ReportController::class, 'emailReport'])->name('email')->middleware('pro:report_email');
                Route::get('schedule', [ReportController::class, 'schedule'])->name('schedule');
                Route::put('schedule', [ReportController::class, 'saveSchedule'])->name('schedule.save')->middleware('pro:report_email');
                Route::delete('schedule', [ReportController::class, 'deleteSchedule'])->name('schedule.delete');
            });

            // Histórico de preços — exclusivo do plano Pro
            Route::prefix('price-history')->name('price-history.')->middleware('pro:price_history')->group(function () {
                Route::get('/', [PriceHistoryController::class, 'search'])->name('search');
                Route::get('timeline', [PriceHistoryController::class, 'timeline'])->name('timeline');
            });

            // Comparativo de preços por cidade/mercado — exclusivo do plano Pro
            Route::prefix('price-comparison')->name('price-comparison.')->middleware('pro:price_comparison')->group(function () {
                Route::get('search-products', [PriceComparisonController::class, 'searchProducts'])->name('search-products');
                Route::get('units', [PriceComparisonController::class, 'units'])->name('units');
                Route::get('by-city', [PriceComparisonController::class, 'byCity'])->name('by-city');
                Route::get('by-issuer', [PriceComparisonController::class, 'byIssuer'])->name('by-issuer');
            });

            // Apelidos de produto (unificação de nome entre lojas)
            Route::prefix('product-aliases')->name('product-aliases.')->group(function () {
                Route::get('suggestions', [ProductAliasController::class, 'suggestions'])->name('suggestions');
                Route::get('suggestions-all', [ProductAliasController::class, 'suggestionsAll'])->name('suggestions-all');
                Route::get('community-suggestions', [ProductAliasController::class, 'communitySuggestions'])->name('community-suggestions');
                Route::post('/', [ProductAliasController::class, 'store'])->name('store');
                Route::post('merge', [ProductAliasController::class, 'merge'])->name('merge');
                Route::post('dismiss', [ProductAliasController::class, 'dismiss'])->name('dismiss');
                Route::post('ai-suggest-name', [ProductAliasController::class, 'aiSuggestName'])
                    ->name('ai-suggest-name')
                    ->middleware(['throttle:ai-suggestions', 'pro:ai_suggestions']);
            });

            // Compras recorrentes — exclusivo do plano Pro
            Route::prefix('recurring-purchases')->name('recurring-purchases.')->middleware('pro:recurring_purchases')->group(function () {
                Route::get('/', [RecurringPurchaseController::class, 'index'])->name('index');
                Route::post('add-to-list', [RecurringPurchaseController::class, 'addToShoppingList'])->name('add-to-list');
                Route::post('replenishment-list', [RecurringPurchaseController::class, 'createReplenishmentList'])->name('replenishment-list');
                Route::post('dismiss', [RecurringPurchaseController::class, 'dismiss'])->name('dismiss');
                Route::post('restore', [RecurringPurchaseController::class, 'restore'])->name('restore');
            });

            // Listas de compras — rotas específicas ANTES do apiResource
            Route::get('shopping-lists/search', [ShoppingListController::class, 'search'])->name('shopping-lists.search');
            Route::get('shopping-lists/cities', [ShoppingListController::class, 'cities'])->name('shopping-lists.cities');
            Route::apiResource('shopping-lists', ShoppingListController::class);
            Route::prefix('shopping-lists/{shoppingList}')->name('shopping-lists.')->group(function () {
                Route::post('duplicate', [ShoppingListController::class, 'duplicate'])->name('duplicate');
                Route::post('purchase-all', [ShoppingListController::class, 'purchaseAll'])->name('purchase-all');
                Route::post('refresh-prices', [ShoppingListController::class, 'refreshPrices'])->name('refresh-prices');
                Route::get('savings', [ShoppingListController::class, 'savings'])->name('savings')->middleware('throttle:20,1');
                Route::post('items', [ShoppingListController::class, 'addItem'])->name('items.add');
                Route::patch('items/{item}', [ShoppingListController::class, 'updateItem'])->name('items.update');
                Route::delete('items/{item}', [ShoppingListController::class, 'removeItem'])->name('items.remove');
                Route::post('items/{item}/toggle-purchased', [ShoppingListController::class, 'togglePurchased'])->name('items.toggle-purchased');
            });

            // Planos: o que cada plano inclui e a assinatura atual
            Route::get('subscription/plans', [SubscriptionController::class, 'plans'])->name('subscription.plans');

            // Conta do usuário
            Route::prefix('account')->name('account.')->group(function () {
                Route::get('/', [AccountController::class, 'show'])->name('show');
                Route::patch('/', [AccountController::class, 'update'])->name('update');
                Route::patch('password', [AccountController::class, 'updatePassword'])->middleware('throttle:5,1')->name('password');
                Route::post('sessions/revoke-others', [AccountController::class, 'revokeOtherSessions'])->middleware('throttle:5,1')->name('sessions.revoke-others');
                Route::post('avatar', [AccountController::class, 'updateAvatar'])->name('avatar');
                Route::post('location-suggestion/dismiss', [AccountController::class, 'dismissLocationSuggestion'])->name('location-suggestion.dismiss');
                Route::post('location/capture', [AccountController::class, 'captureLocation'])->middleware('throttle:10,1')->name('location.capture');
                Route::post('export', [AccountController::class, 'requestExport'])->middleware('throttle:5,60')->name('export');
                Route::delete('/', [AccountController::class, 'destroy'])->middleware('throttle:5,1')->name('destroy');
            });

            // Produtos favoritos (alerta de queda de preço)
            Route::prefix('favorite-products')->name('favorite-products.')->group(function () {
                Route::get('/', [FavoriteProductController::class, 'index'])->name('index');
                Route::post('/', [FavoriteProductController::class, 'store'])->name('store');
                Route::post('toggle', [FavoriteProductController::class, 'toggle'])->name('toggle');
                Route::delete('{favoriteProduct}', [FavoriteProductController::class, 'destroy'])->name('destroy');
            });

            // Notificações
            Route::prefix('notifications')->name('notifications.')->group(function () {
                Route::get('/', [NotificationController::class, 'index'])->name('index');
                Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
                Route::post('read-all', [NotificationController::class, 'markAllAsRead'])->name('read-all');
                Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
            });

        });
    });
});
