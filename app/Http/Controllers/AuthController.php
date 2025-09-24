<?php

// app/Http/Controllers/AuthController.php
namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\QueryException;

class AuthController extends Controller
{
   public function register(Request $r)
    {
        // normalisasi email biar konsisten
        if ($r->has('email')) {
            $r->merge(['email' => mb_strtolower(trim($r->input('email')))]);
        }

        $data = $r->validate([
            'name'  => 'required|string|max:120',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'cf-turnstile-response' => 'nullable|string',
        ]);

        if (filled($data['cf-turnstile-response'] ?? null)) {
            if (! Turnstile::verify($data['cf-turnstile-response'], $r->ip())) {
                return response()->json(['message' => 'Verifikasi manusia gagal'], 422);
            }
        }

        // Jika email sudah ada:
        if ($existing = User::whereEmail($data['email'])->first()) {
            if ($existing->hasVerifiedEmail()) {
                return response()->json([
                    'message' => 'Periksa kembali data yang diisi.',
                    'errors'  => ['email' => ['Email sudah terdaftar.']],
                ], 422);
            }

            // belum verified → kirim ulang via Resend
            app(\App\Services\VerificationMailer::class)->send($existing);

            return response()->json([
                'message' => 'Email sudah terdaftar tapi belum terverifikasi. Tautan verifikasi telah dikirim ulang.',
                'need_verification' => true,
            ], 200);
        }

        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
            ]);

            // KIRIM VERIFIKASI VIA RESEND (JANGAN panggil bawaan Laravel lagi)
            app(\App\Services\VerificationMailer::class)->send($user);

            return response()->json([
                'message' => 'Registrasi diterima. Cek email untuk verifikasi.',
            ], 201);

        } catch (QueryException $e) {
            // race condition guard (Postgres duplicate key)
            if ($e->getCode() === '23505') {
                return response()->json([
                    'message' => 'Periksa kembali data yang diisi.',
                    'errors'  => ['email' => ['Email sudah terdaftar.']],
                ], 422);
            }
            throw $e;
        }
    }

    public function login(Request $r)
    {
        $data = $r->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::whereEmail($data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // blokir jika belum verifikasi
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email belum terverifikasi.',
                'need_verification' => true,
            ], 403);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user'  => $user,
        ]);
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }

    public function google(Request $r)
    {
        $data = $r->validate([
            'id_token' => 'required|string',
            'cf-turnstile-response' => 'required|string',
        ]);

        if (! Turnstile::verify($data['cf-turnstile-response'], $r->ip())) {
            return response()->json(['message' => 'Verifikasi manusia gagal'], 422);
        }

        $client = new \Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
        $payload = $client->verifyIdToken($data['id_token']);
        if (! $payload) {
            return response()->json(['message' => 'Invalid Google token'], 401);
        }

        $googleId = $payload['sub'] ?? null;
        $email    = $payload['email'] ?? null;
        $name     = $payload['name']  ?? ($payload['given_name'] ?? 'User');

        if (! $googleId || ! $email) {
            return response()->json(['message' => 'Google payload incomplete'], 422);
        }

        $user = User::where('google_id', $googleId)->orWhere('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name'      => $name,
                'email'     => $email,
                'google_id' => $googleId,
                'password'  => bcrypt(str()->random(40)),
                // kalau mau, anggap verified bila $payload['email_verified'] === true
                'email_verified_at' => ($payload['email_verified'] ?? false) ? now() : null,
            ]);
        } elseif (! $user->google_id) {
            $user->google_id = $googleId;
            $user->save();
        }

        // kalau belum verified dan email_verified==false, block
        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email Google belum terverifikasi.'], 403);
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user'  => $user,
        ]);
    }
}
