<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileSecurityController extends Controller
{
    // app/Http/Controllers/ProfileSecurityController.php
    public function updatePassword(Request $r)
    {
        $user = $r->user();

        $isGoogleOnly = $user->google_id && is_null($user->password_set_at);

        $rules = [
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
        if (! $isGoogleOnly) {
            $rules['current_password'] = ['required', 'string'];
        }

        $data = $r->validate($rules);

        if (! $isGoogleOnly) {
            if (! Hash::check($data['current_password'], $user->password)) {
                return response()->json([
                    'message' => 'Password saat ini salah.',
                    'errors'  => ['current_password' => ['Password saat ini salah.']],
                ], 422);
            }
        }

        $user->password = bcrypt($data['password']);
        if ($isGoogleOnly) {
            $user->password_set_at = now(); // tandai sudah punya password lokal
        }
        $user->save();

        return response()->json(['ok' => true, 'message' => $isGoogleOnly ? 'Password berhasil dibuat.' : 'Password berhasil diganti.']);
    }
}
