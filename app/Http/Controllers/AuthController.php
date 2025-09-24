<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Turnstile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

use Illuminate\Validation\ValidationException;
use Google_Client; // <— pastikan di bagian atas file

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
            'cf-turnstile-response' => 'required|string', // <— wajib
        ]);

        // 1) Verifikasi Turnstile dulu
        if (!Turnstile::verify($data['cf-turnstile-response'], $r->ip())) {
            return response()->json(['message' => 'Verifikasi manusia gagal'], 422);
        }

        // 2) Baru verifikasi Google ID Token
        $client = new Google_Client(['client_id' => env('GOOGLE_CLIENT_ID')]);
        $payload = $client->verifyIdToken($data['id_token']);
        if (!$payload) {
            return response()->json(['message' => 'Invalid Google token'], 401);
        }

        $googleId = $payload['sub'] ?? null;
        $email    = $payload['email'] ?? null;
        $name     = $payload['name']  ?? ($payload['given_name'] ?? 'User');

        if (!$googleId || !$email) {
            return response()->json(['message' => 'Google payload incomplete'], 422);
        }

        $user = User::where('google_id', $googleId)
            ->orWhere('email', $email)
            ->first();

        if (!$user) {
            $user = User::create([
                'name'      => $name,
                'email'     => $email,
                'google_id' => $googleId,
                'password'  => bcrypt(str()->random(40)),
            ]);
        } elseif (!$user->google_id) {
            $user->google_id = $googleId;
            $user->save();
        }

        return response()->json([
            'token' => $user->createToken('api')->plainTextToken,
            'user'  => $user,
        ]);
    }
}
