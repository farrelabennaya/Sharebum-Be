<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\{Album, Asset};
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

// app/Http/Controllers/AlbumController.php
class AlbumController extends Controller
{
    public function index(Request $r)
    {
        $q = Album::where('user_id', $r->user()->id)->withCount(['pages', 'comments', 'reactions']);
        return $q->orderByDesc('id')->paginate(12);
    }
    public function store(Request $r)
    {
        $data = $r->validate([
            'title' => 'required|string|max:120',
            'idol_id' => 'nullable|exists:idols,id',
            'is_public' => 'boolean',
            'theme' => 'array'
        ]);
        $slug = Str::slug($data['title']) . '-' . Str::random(6);
        $album = Album::create(array_merge($data, [
            'user_id' => $r->user()->id,
            'slug' => $slug
        ]));
        return $album->fresh();
    }
    // app/Http/Controllers/AlbumController.php
    public function show(Request $r, $id)
    {
        $album = \App\Models\Album::query()
            ->whereKey($id)
            ->where('user_id', $r->user()->id) // ← pakai Request → user → id
            ->with([
                'pages' => fn($q) => $q->orderBy('index'),
                'pages.assets' => fn($q) => $q->orderBy('order'),
                'pages.assets.tags:id,name',
            ])
            ->firstOrFail();

        return $album;
    }

    public function update(Request $r, $id)
    {
        $data = $r->validate([
            'title'      => 'sometimes|string|max:255',
            'visibility' => 'sometimes|in:private,unlisted,public',
            'password' => 'sometimes|nullable|string',
        ]);

        $album = Album::where('id', $id)->where('user_id', $r->user()->id)->firstOrFail();

        if (array_key_exists('visibility', $data)) {
            $album->visibility = $data['visibility'];
        }

        if ($r->has('password')) {
            $pass = $data['password']; // bisa null atau string
            $album->password_hash = ($pass === null || $pass === '')
                ? null
                : Hash::make($pass);
        }

        if (array_key_exists('title', $data)) {
            $album->title = $data['title'];
        }

        $album->save();
        return $album->fresh();
    }

    public function destroy(Request $r, $id)
    {
        $album = Album::findOrFail($id);
        abort_unless($album->user_id === $r->user()->id, 403);
        $album->delete();
        return response()->noContent();
    }

    public function setCover(Request $r, Album $album)
    {
        abort_unless($album->user_id === $r->user()->id, 403);

        $data = $r->validate([
            'asset_id' => 'required|exists:assets,id',
        ]);

        $asset = Asset::with('page')->findOrFail($data['asset_id']);
        abort_unless($asset->page->album_id === $album->id, 403);

        // pakai varian 'md' kalau ada, fallback ke url asli
        $cover = is_array($asset->variants) && ($asset->variants['md'] ?? null)
            ? $asset->variants['md']
            : $asset->url;

        $album->cover_url = $cover;
        $album->save();

        return $album->fresh();
    }
}
