<?php

// app/Http/Controllers/EmailVerificationController.php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\Verified;

class EmailVerificationController extends Controller
{
    public function verify(Request $request, $id, $hash)
    {
        // validasi signature di URL
        if (! URL::hasValidSignature($request)) {
            return response()->json(['message' => 'Invalid/expired link'], 403);
        }

        $user = User::findOrFail($id);

        // pastikan hash cocok (laravel default pakai sha1(email))
        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json(['message' => 'Invalid hash'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        // Redirect ke FE (SPA) dengan status sukses
        $front = rtrim(config('app.frontend_url', env('FRONTEND_URL', '/')), '/');
        return redirect()->away($front . '/verified?ok=1');
    }

    // resend via email (tanpa login) — rate limit di route
    public function resend(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', $data['email'])->first();

        // jangan bocorkan apakah email ada/tidak
        if ($user && ! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Jika email terdaftar & belum terverifikasi, tautan verifikasi telah dikirim.']);
    }
}

