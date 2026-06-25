<?php

namespace App\Providers;

use App\Events\InvoiceImported;
use App\Listeners\AutoCategorizeListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Event::listen(InvoiceImported::class, AutoCategorizeListener::class);
    }
}
