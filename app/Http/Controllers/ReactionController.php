<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Reaction;
use Illuminate\Http\Request;

class ReactionController extends Controller
{
    public function toggle(Request $r, Album $album)
    {
        $user = $r->user();
        // hanya pemilik album? -> tidak. Siapapun login boleh react
        // tapi yang private tidak boleh diakses publik
        abort_if($album->visibility === 'private' && $album->user_id !== $user->id, 403);

        $existing = Reaction::where('album_id', $album->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $reacted = false;
        } else {
            Reaction::create([
                'album_id' => $album->id,
                'user_id'  => $user->id,
            ]);
            $reacted = true;
        }

        $count = Reaction::where('album_id', $album->id)->count();

        return response()->json([
            'reacted' => $reacted,
            'count' => $count,
        ]);
    }
}
