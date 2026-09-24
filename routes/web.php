<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FavoriteProductController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\IssuerController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\MyPurchaseController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PricesController;
use App\Http\Controllers\ProductAliasController;
use App\Http\Controllers\RecurringPurchaseController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ShoppingListController;
use App\Http\Controllers\SocialAuthController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TermsAcceptanceController;
use App\Http\Controllers\VerificationController;
use App\Models\File;
use App\Models\User;
use App\Notifications\PersonalDataExportReady;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard.index');
});

Route::group(['prefix' => 'forgot-password', 'as' => 'password.'], function () {
    Route::get('/', [ForgotPasswordController::class, 'index'])->name('request');
    Route::post('/', [ForgotPasswordController::class, 'send'])->name('email')->middleware('throttle:5,1');
});

Route::get('/reset-password', [ResetPasswordController::class, 'index'])->name('password.reset');
Route::get('/reset-password/app', [ResetPasswordController::class, 'openApp'])->name('password.reset.app');
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

Route::get('/termos-de-uso', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/politica-de-privacidade', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/excluir-conta', [LegalController::class, 'deleteAccount'])->name('legal.delete-account');

Route::group(['prefix' => 'email/verify', 'as' => 'verification.', 'middleware' => 'auth'], function () {
    Route::get('/', [VerificationController::class, 'notice'])->name('notice');
    Route::get('{id}/{hash}', [VerificationController::class, 'verify'])->name('verify')->middleware(['signed', 'throttle:6,1']);
    Route::post('resend', [VerificationController::class, 'resend'])->name('send')->middleware('throttle:6,1');
});

Route::group(['prefix' => 'aceitar-termos', 'as' => 'terms.accept.', 'middleware' => 'auth'], function () {
    Route::get('/', [TermsAcceptanceController::class, 'form'])->name('form');
    Route::post('/', [TermsAcceptanceController::class, 'store'])->name('store');
});

Route::group(['middleware' => ['auth', 'verified', 'terms.accepted']], function () {

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
        Route::delete('{invoice}', [MyPurchaseController::class, 'destroy'])->name('destroy');
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
        Route::post('revert-auto-categorization', [CategoryController::class, 'revertAutoCategorization'])->name('revert-auto-categorization');
        Route::post('preview-keywords', [CategoryController::class, 'previewKeywords'])->name('preview-keywords');
        Route::get('uncategorized', [CategoryController::class, 'uncategorized'])->name('uncategorized');
        Route::get('{category}', [CategoryController::class, 'show'])->name('show');
        Route::post('{category}/merge', [CategoryController::class, 'merge'])->name('merge');
        Route::post('suggest-item-category', [CategoryController::class, 'suggestItemCategory'])
            ->name('suggest-item-category')
            ->middleware(['throttle:ai-suggestions', 'pro:ai_suggestions']);
        Route::post('suggest-keywords', [CategoryController::class, 'suggestKeywords'])
            ->name('suggest-keywords')
            ->middleware(['throttle:ai-suggestions', 'pro:ai_suggestions']);
    });

    // Comparação e histórico de preços entre lojas — exclusivo do plano Pro.
    Route::group(['prefix' => 'prices', 'as' => 'prices.', 'middleware' => 'pro:price_comparison'], function () {
        Route::get('/', [PricesController::class, 'index'])->name('index');
        Route::get('search', [PricesController::class, 'search'])->name('search');
        Route::get('history', [PricesController::class, 'history'])->name('history');
        Route::get('units', [PricesController::class, 'units'])->name('units');
        Route::get('by-city', [PricesController::class, 'byCity'])->name('by-city');
        Route::get('by-issuer', [PricesController::class, 'byIssuer'])->name('by-issuer');
    });

    // Rotas antigas, mantidas como redirect (301) para não quebrar links/favoritos salvos.
    Route::get('/price-history', fn (Request $request) => redirect()->route('prices.index', $request->query(), 301));
    Route::get('/price-comparison', fn (Request $request) => redirect()->route('prices.index', $request->query(), 301));

    Route::group(['prefix' => 'product-aliases', 'as' => 'product-aliases.'], function () {
        Route::get('review', [ProductAliasController::class, 'review'])->name('review');
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

    Route::get('search', [SearchController::class, 'search'])->name('search');

    Route::group(['prefix' => 'budgets', 'as' => 'budgets.'], function () {
        Route::get('/', [BudgetController::class, 'index'])->name('index');
        Route::post('/', [BudgetController::class, 'store'])->name('store');
        Route::delete('{budget}', [BudgetController::class, 'destroy'])->name('destroy');
    });

    Route::group(['prefix' => 'reports', 'as' => 'reports.'], function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::post('generate', [ReportController::class, 'generate'])->name('generate');
        // GET e POST: os botões de exportar são links (mantêm os filtros na URL); o POST antigo continua valendo.
        Route::match(['get', 'post'], 'pdf', [ReportController::class, 'exportPdf'])->name('pdf')->middleware('pro:report_pdf');
        Route::match(['get', 'post'], 'csv', [ReportController::class, 'exportCsv'])->name('csv')->middleware('pro:report_csv');
        Route::post('email', [ReportController::class, 'email'])->name('email')->middleware('pro:report_email');
        Route::put('schedule', [ReportController::class, 'saveSchedule'])->name('schedule.save')->middleware('pro:report_email');
        Route::delete('schedule', [ReportController::class, 'deleteSchedule'])->name('schedule.delete');
    });

    // Detecção de compras recorrentes — exclusivo do plano Pro.
    Route::group(['prefix' => 'recurring-purchases', 'as' => 'recurring-purchases.', 'middleware' => 'pro:recurring_purchases'], function () {
        Route::get('/', [RecurringPurchaseController::class, 'index'])->name('index');
        Route::post('add-to-list', [RecurringPurchaseController::class, 'addToShoppingList'])->name('add-to-list');
        Route::post('replenishment-list', [RecurringPurchaseController::class, 'createReplenishmentList'])->name('replenishment-list');
        Route::post('dismiss', [RecurringPurchaseController::class, 'dismiss'])->name('dismiss');
        Route::post('restore', [RecurringPurchaseController::class, 'restore'])->name('restore');
    });

    Route::group(['prefix' => 'shopping-list', 'as' => 'shopping-list.'], function () {
        Route::get('/', [ShoppingListController::class, 'index'])->name('index');
        Route::get('search', [ShoppingListController::class, 'search'])->name('search');
        Route::get('cities', [ShoppingListController::class, 'cities'])->name('cities');
        Route::post('/', [ShoppingListController::class, 'store'])->name('store');
        Route::get('{shoppingList}', [ShoppingListController::class, 'show'])->name('show');
        Route::patch('{shoppingList}', [ShoppingListController::class, 'update'])->name('update');
        Route::delete('{shoppingList}', [ShoppingListController::class, 'destroy'])->name('destroy');
        Route::post('{shoppingList}/duplicate', [ShoppingListController::class, 'duplicate'])->name('duplicate');
        Route::post('{shoppingList}/purchase-all', [ShoppingListController::class, 'purchaseAll'])->name('purchase-all');
        Route::post('{shoppingList}/refresh-prices', [ShoppingListController::class, 'refreshPrices'])->name('refresh-prices')->middleware('throttle:30,1');
        Route::get('{shoppingList}/savings', [ShoppingListController::class, 'savings'])->name('savings')->middleware('throttle:20,1');
        Route::post('{shoppingList}/items', [ShoppingListController::class, 'addItem'])->name('items.add');
        Route::patch('{shoppingList}/items/{item}', [ShoppingListController::class, 'updateItem'])->name('items.update');
        Route::delete('{shoppingList}/items/{item}', [ShoppingListController::class, 'removeItem'])->name('items.remove');
        Route::post('{shoppingList}/items/{item}/toggle-purchased', [ShoppingListController::class, 'togglePurchased'])->name('items.toggle-purchased');
    });

    Route::group(['prefix' => 'account', 'as' => 'account.'], function () {
        Route::get('/', [AccountController::class, 'index'])->name('index');
        Route::patch('/', [AccountController::class, 'update'])->name('update');
        Route::patch('password', [AccountController::class, 'updatePassword'])->middleware('throttle:5,1')->name('password');
        Route::post('sessions/revoke-others', [AccountController::class, 'revokeOtherSessions'])->middleware('throttle:5,1')->name('sessions.revoke-others');
        Route::post('avatar', [AccountController::class, 'updateAvatar'])->name('avatar');
        Route::post('location-suggestion/dismiss', [AccountController::class, 'dismissLocationSuggestion'])->name('location-suggestion.dismiss');
        Route::post('location/capture', [AccountController::class, 'captureLocation'])->middleware('throttle:10,1')->name('location.capture');
        Route::post('export', [AccountController::class, 'requestExport'])->middleware('throttle:5,60')->name('export');
        Route::get('export/download/{file}', [AccountController::class, 'downloadExport'])->middleware('signed')->name('export.download');
        Route::delete('/', [AccountController::class, 'destroy'])->middleware('throttle:5,1')->name('destroy');
    });

    Route::group(['prefix' => 'favorite-products', 'as' => 'favorite-products.'], function () {
        Route::get('/', [FavoriteProductController::class, 'index'])->name('index');
        Route::post('/', [FavoriteProductController::class, 'store'])->name('store');
        Route::post('toggle', [FavoriteProductController::class, 'toggle'])->name('toggle');
        Route::delete('{favoriteProduct}', [FavoriteProductController::class, 'destroy'])->name('destroy');
    });

    Route::group(['prefix' => 'notifications', 'as' => 'notifications.'], function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
    });

    Route::get('subscription/upgrade', [SubscriptionController::class, 'upgrade'])->name('subscription.upgrade');

    // Painel super simples de promoção manual a Pro — só o super admin (config('subscription.super_admin_email')) acessa.
    Route::group(['prefix' => 'admin/subscriptions', 'as' => 'admin.subscriptions.', 'middleware' => 'super-admin'], function () {
        Route::get('/', [AdminSubscriptionController::class, 'index'])->name('index');
        Route::patch('{user}', [AdminSubscriptionController::class, 'update'])->name('update');
    });

});

// Preview dos layouts de e-mail — só em local/staging.
if (app()->environment(['local', 'staging'])) {
    Route::get('mail-preview/{email?}', function (?string $email = null) {
        $user = new User(['name' => 'Usuário Exemplo', 'email' => 'exemplo@example.com']);
        $user->id = 1;

        $emails = [
            'verify-email' => fn () => (new VerifyEmailNotification)->toMail($user),
            'reset-password' => fn () => (new ResetPassword('token-exemplo'))->toMail($user),
            'data-export' => fn () => (new PersonalDataExportReady((new File)->forceFill(['id' => 1])))->toMail($user),
        ];

        if (! isset($emails[$email])) {
            return collect(array_keys($emails))
                ->map(fn ($key) => '<li><a href="'.url("mail-preview/{$key}").'">'.$key.'</a></li>')
                ->prepend('<h1>E-mails</h1><ul>')->push('</ul>')->implode('');
        }

        return $emails[$email]();
    })->name('mail-preview');
}
