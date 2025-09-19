<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Comment;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function store(Request $r, $slug)
    {
        $user = $r->user();
        $album = Album::where('slug', $slug)->firstOrFail();

        // hanya public/unlisted, atau owner jika private
        abort_if($album->visibility === 'private' && $album->user_id !== $user->id, 403);

        $data = $r->validate([
            'body' => 'required|string|max:2000',
        ]);

        $c = Comment::create([
            'album_id' => $album->id,
            'user_id'  => $user->id,
            'body'     => $data['body'],
        ]);

        return response()->json([
            'id' => $c->id,
            'body' => $c->body,
            'user_name' => $user->name ?? 'User',
            'created_at' => $c->created_at,
        ], 201);
    }


    public function destroy(Request $r, Comment $comment)
    {
        $user = $r->user();
        // boleh hapus kalau pemilik comment atau pemilik album
        abort_unless($comment->user_id === $user->id || $comment->album->user_id === $user->id, 403);

        $comment->delete();
        return response()->noContent();
    }
}
