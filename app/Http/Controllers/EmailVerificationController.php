<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailVerificationController extends Controller
{
    public function verify(EmailVerificationRequest $request)
    {
        $user = User::findOrFail($request->route('id'));

        // validasi hash email
        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $request->route('hash'))) {
            return redirect(config('app.frontend_url').'/verified?ok=0');
        }

        // sudah verified?
        if ($user->hasVerifiedEmail()) {
            return redirect(config('app.frontend_url').'/verified?ok=1');
        }

        // signature valid? (middleware 'signed' juga sudah cek, ini tambahan aman)
        if (! $request->hasValidSignature()) {
            return redirect(config('app.frontend_url').'/verified?ok=0');
        }

        // tandai verified
        $user->markEmailAsVerified();
        event(new Verified($user));

        return redirect(config('app.frontend_url').'/verified?ok=1');
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
                Log::error('Resend verify email failed: '.$e->getMessage());
                // tetap balas generik
            }
        }

        return response()->json(['message' => 'Jika email terdaftar, tautan verifikasi telah dikirim.']);
    }
}
