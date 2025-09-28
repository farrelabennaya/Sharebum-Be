<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileSecurityController extends Controller
{
    public function updatePassword(Request $r)
    {
        $user = $r->user();

        $data = $r->validate([
            'current_password'      => ['required','string'],
            'password'              => ['required','string','min:8','confirmed'], // kirim password_confirmation juga
        ]);

        // cek current password
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Password saat ini salah.',
                'errors'  => ['current_password' => ['Password saat ini salah.']],
            ], 422);
        }

        // update
        $user->password = bcrypt($data['password']);
        $user->save();

        return response()->json(['ok' => true, 'message' => 'Password berhasil diganti.']);
    }
}
