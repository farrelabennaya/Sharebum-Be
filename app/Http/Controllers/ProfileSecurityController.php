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

        // Wajib minta current_password hanya jika user SUDAH punya password lokal
        // pakai flag yang stabil:
        $requiresCurrent = (bool) $user->password_set_at;   // atau: $user->has_password

        $rules = [
            'password' => ['required', 'confirmed', Password::min(8)],
        ];

        if ($requiresCurrent) {
            // Laravel akan cek otomatis terhadap password user aktif
            $rules['current_password'] = ['required', 'current_password'];
        }

        $data = $r->validate($rules);

        // (opsional) larang password baru sama dengan lama jika sudah punya password
        if ($requiresCurrent && Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => 'Password baru tidak boleh sama dengan password saat ini.',
                'errors'  => ['password' => ['Password baru tidak boleh sama dengan password saat ini.']],
            ], 422);
        }

        // Set password baru
        $user->password = Hash::make($data['password']);

        // Jika ini pertama kali (akun Google), tandai sudah memiliki password lokal
        if (! $requiresCurrent) {
            $user->password_set_at = now();
        }

        $user->save();

        return response()->json([
            'ok'      => true,
            'message' => $requiresCurrent ? 'Password berhasil diganti.' : 'Password berhasil dibuat.',
        ]);
    }
}
