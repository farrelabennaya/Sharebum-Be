<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileSecurityController extends Controller
{
    public function updatePassword(Request $r)
    {
        $user = $r->user();

        // Akun Google yang belum pernah set password lokal
        $isGoogleOnly = !filled($user->password) && filled($user->google_id);

        $rules = [
            'password' => [
                'required',
                'confirmed',
                Password::min(8), // bisa tambah: ->mixedCase()->numbers()->symbols()
            ],
        ];

        if (!$isGoogleOnly) {
            // Wajib verifikasi password lama utk akun yang sudah punya password
            $rules['current_password'] = ['required', 'current_password'];
        }

        $data = $r->validate($rules);

        // (Opsional) Larang password baru sama dengan yang lama (untuk akun yg sdh punya password)
        if (!$isGoogleOnly && Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Password baru tidak boleh sama dengan password saat ini.',
                'errors'  => ['password' => ['Password baru tidak boleh sama dengan password saat ini.']],
            ], 422);
        }

        // Set password baru
        $user->password = Hash::make($data['password']);

        // Tandai bahwa user ini sudah punya password lokal (untuk akun Google)
        if ($isGoogleOnly) {
            $user->password_set_at = now();
        }

        $user->save();

        return response()->json([
            'ok'      => true,
            'message' => $isGoogleOnly ? 'Password berhasil dibuat.' : 'Password berhasil diganti.',
        ]);
    }
}
