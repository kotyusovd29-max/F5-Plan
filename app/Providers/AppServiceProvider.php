<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;
use PhpOffice\PhpWord\Settings as PhpWordSettings;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::defaultView('vendor.pagination.simple');

        // Иначе &, <, > в тексте ломают word/document.xml — Google Docs открывает как CORRUPTED.
        PhpWordSettings::setOutputEscapingEnabled(true);
    }
}
