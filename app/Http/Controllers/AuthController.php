<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Google_Client; // <— pastikan di bagian atas file
use Illuminate\Validation\ValidationException;

use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user'  => $user,
        ]);
    }

    public function login(Request $r)
    {
        $data = $r->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::whereEmail($data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
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
        ]);

        $client = new Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);

        // Verifikasi ID Token ke Google
        $payload = $client->verifyIdToken($data['id_token']);
        if (!$payload) {
            return response()->json(['message' => 'Invalid Google token'], 401);
        }

        // Data penting dari payload
        $googleId = $payload['sub'] ?? null;          // unique id Google
        $email    = $payload['email'] ?? null;
        $name     = $payload['name']  ?? ($payload['given_name'] ?? 'User');
        $verified = $payload['email_verified'] ?? false;

        if (!$googleId || !$email) {
            return response()->json(['message' => 'Google payload incomplete'], 422);
        }

        // OPSIONAL: jika ingin hanya email terverifikasi
        // if (!$verified) {
        //     return response()->json(['message' => 'Email is not verified by Google'], 401);
        // }

        // Cari user berdasarkan google_id ATAU email (untuk "link" otomatis jika sebelumnya sudah register manual)
        $user = User::where('google_id', $googleId)->orWhere('email', $email)->first();

        if (!$user) {
            // Buat user baru
            $user = User::create([
                'name'      => $name,
                'email'     => $email,
                'google_id' => $googleId,
                'password'  => bcrypt(str()->random(40)), // dummy
            ]);
        } else {
            // Update google_id jika belum ada
            if (!$user->google_id) {
                $user->google_id = $googleId;
                $user->save();
            }
        }

        // Keluarkan token Sanctum (sama seperti login/register kamu)
        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user'  => $user,
        ]);
    }
}
