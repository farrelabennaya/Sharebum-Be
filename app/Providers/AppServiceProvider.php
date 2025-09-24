<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    // app/Providers/AppServiceProvider.php
    public function register()
    {
        $this->app->singleton(\Resend\Resend::class, fn() => new \Resend\Resend(env('RESEND_API_KEY')));
    }


    public function boot(): void {}
}
