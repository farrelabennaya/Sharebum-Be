<?php

namespace App\Providers;
use Illuminate\Support\Facades\URL;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // app/Providers/AppServiceProvider.php
    public function register()
    {
    }


    public function boot(): void
{
    if (app()->environment('production')) {
        URL::forceScheme('https');
    }
}
}
