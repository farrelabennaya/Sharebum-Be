<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $u = $request->user();

        return response()->json([
            'id'         => $u->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'avatar_url' => $u->avatar_url,
            'created_at' => $u->created_at,
        ]);
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $user->fill($request->only(['name','email']))->save();

        return response()->json([
            'message' => 'Profil diperbarui.',
            'user'    => [
                'id'         => $user->id,
                'name'       => $user->name,
                'email'      => $user->email,
                'avatar_url' => $user->avatar_url,
                'created_at' => $user->created_at,
            ],
        ]);
    }
}
