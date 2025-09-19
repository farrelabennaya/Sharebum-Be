<?php

namespace App\Http\Controllers;

use App\Models\Album;
use Illuminate\Http\Request;

class ShareController extends Controller
{
    public function page(Request $r, $slug)
{
    $album = Album::where('slug',$slug)->firstOrFail();

    // jangan bocorkan cover untuk album ber-password
    $isLocked = (bool)$album->password_hash;
    $og = [
        'title' => $album->title ?: 'Album',
        'desc'  => $isLocked ? 'Album terkunci dengan kata sandi.' : 'Lihat foto-fotonya.',
        'image' => $isLocked ? asset('img/og-default.jpg') : ($album->cover_url ?: asset('img/og-default.jpg')),
        'url'   => url("/album/{$album->slug}"),
    ];
    return response()->view('public_album', compact('og'));
}

}
