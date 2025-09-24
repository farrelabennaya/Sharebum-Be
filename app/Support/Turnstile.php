<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class Turnstile
{
    public static function verify(string $token, ?string $ip = null): bool
    {
        $secret = config('services.turnstile.secret', env('TURNSTILE_SECRET'));
        if (!$secret) return false;

        $resp = Http::asForm()->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            [
                'secret'   => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ]
        );

        return $resp->ok() && (($resp->json()['success'] ?? false) === true);
    }
}
