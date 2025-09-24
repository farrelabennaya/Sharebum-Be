<?php

namespace App\Providers;

use Resend;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
   public function register()
{
    $this->app->singleton(Resend::class, fn () => new Resend(env('RESEND_API_KEY')));
}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
