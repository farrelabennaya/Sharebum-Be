<?php

// app/Http/Middleware/Authenticate.php
namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        // Untuk request API, jangan redirect—biarkan lempar 401
        if ($request->expectsJson() || $request->is('api/*')) {
            return null;
        }

        // Kalau ada halaman login web, arahkan ke sana (opsional)
        // return route('login');

        // Atau, kalau SPA login di "/", arahkan ke "/"
        return url('/');
    }
}

