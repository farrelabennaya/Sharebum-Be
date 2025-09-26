<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
// use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    public function verify(Request $request)
    {
        $user = \App\Models\User::findOrFail($request->route('id'));

        // validasi hash & signature
        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            return redirect(config('app.frontend_url') . '/verified?ok=0');
        }
        if (! $request->hasValidSignature()) {
            return redirect(config('app.frontend_url') . '/verified?ok=0');
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        // auto-login setelah verify (opsional)
        $token = $user->createToken('api')->plainTextToken;

        return redirect(
            rtrim(config('app.frontend_url'), '/') . '/verified?ok=1&token=' . $token
        );
    }


    public function resend(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $user = User::where('email', $data['email'])->first();

        // selalu balas generik agar tidak bocorkan keberadaan email
        if ($user && ! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification(); // <<— pakai SMTP Brevo
            } catch (\Throwable $e) {
                Log::error('Resend verify email failed: ' . $e->getMessage());
                // tetap balas generik
            }
        }

        return response()->json(['message' => 'Jika email terdaftar, tautan verifikasi telah dikirim.']);
    }
}
