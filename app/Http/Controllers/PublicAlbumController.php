<?php

namespace App\Http\Controllers;

use App\Models\{Album, Asset, Reaction, Comment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\User; // kalau pakai with('user:...')


class PublicAlbumController extends Controller
{
    // ===== ALBUM PAGE =====
    public function show(Request $r, string $slug)
    {
        $album = Album::where('slug', $slug)
            ->with([
                'pages' => fn($q) => $q->orderBy('index'),
                // 'pages.assets' => fn($q) => $q->orderBy('order'),
                'pages.assets' => fn($q) => $q->orderBy('order')->with('tags:id,name'),
            ])
            ->firstOrFail();

        abort_unless(in_array($album->visibility, ['public', 'unlisted']), 403);

        if ($album->password_hash) {
            $pass = $r->header('X-Album-Pass') ?? $r->input('password');
            if (!$pass || !Hash::check($pass, $album->password_hash)) {
                return response()->json(['message' => 'PASSWORD_REQUIRED'], 401);
            }
        }

        // album-level reaction breakdown
        $breakdown = Reaction::where('album_id', $album->id)
            ->selectRaw('emoji, COUNT(*) as c')
            ->groupBy('emoji')
            ->pluck('c', 'emoji');

        $selected = null;
        if ($r->user()) {
            $selected = Reaction::where('album_id', $album->id)
                ->where('user_id', $r->user()->id)
                ->value('emoji'); // null kalau belum pilih
        }

        // album-level comments (tanpa asset)
        $comments = Comment::where('album_id', $album->id)
            ->whereNull('asset_id')
            ->where('is_hidden', false)
            ->latest()
            ->limit(50)
            ->with('user:id,name')
            ->get()
            ->map(fn($c) => [
                'id' => $c->id,
                'body' => $c->body,
                'user_name' => $c->user?->name ?? 'Anon',
                'created_at' => $c->created_at,
            ]);

        return response()->json([
            'id'                 => $album->id,
            'title'              => $album->title,
            'slug'               => $album->slug,
            'cover_url'          => $album->cover_url,
            'visibility'         => $album->visibility,
            'password_protected' => (bool)$album->password_hash,
            'pages'              => $album->pages,
            'comments'           => $comments,
            'reactions_breakdown' => (object) $breakdown,
            'selected_emoji'     => $selected, // FE pakai untuk ikon tombol
        ]);
    }

    public function reactAlbum(Request $r, string $slug)
    {
        $user = $r->user();
        $album = Album::where('slug', $slug)->firstOrFail();
        $emoji = $r->input('emoji', '❤');

        // toggle single-emoji untuk ALBUM: pakai album_id, asset_id = NULL
        $row = Reaction::where('user_id', $user->id)->where('album_id', $album->id)->first();

        if ($row && $row->emoji === $emoji) {
            $row->delete(); // OFF
            $selected = null;
        } else {
            if ($row) {
                $row->emoji = $emoji;
                $row->save();
            } else {
                Reaction::create([
                    'user_id'  => $user->id,
                    'album_id' => $album->id,
                    'asset_id' => null,
                    'emoji'    => $emoji,
                ]);
            }
            $selected = $emoji;
        }

        $counts = Reaction::where('album_id', $album->id)
            ->selectRaw('emoji, COUNT(*) as c')
            ->groupBy('emoji')
            ->pluck('c', 'emoji');

        return response()->json([
            'selected_emoji' => $selected,
            'counts'         => $counts,
        ]);
    }

    public function commentAlbum(Request $r, string $slug)
    {
        $user = $r->user();
        $album = Album::where('slug', $slug)->firstOrFail();

        $data = $r->validate(['body' => 'required|string|min:1|max:1000']);

        $c = Comment::create([
            'user_id'  => $user->id,
            'album_id' => $album->id,
            'asset_id' => null,
            'body'     => $data['body'],
        ]);

        return response()->json([
            'id'         => $c->id,
            'body'       => $c->body,
            'user_name'  => $user->name ?? 'Anon',
            'created_at' => $c->created_at,
        ], 201);
    }

    // ===== ASSET (per gambar) =====
    public function showAsset(Request $r, string $slug, Asset $asset)
    {
        // 1) Ambil album by slug
        $album = Album::where('slug', $slug)->firstOrFail();

        // 2) Pastikan asset belong to album (lewat relasi page->album_id atau fallback album_id di asset)
        $assetAlbumId = $asset->page->album_id ?? $asset->album_id ?? null;
        abort_unless($assetAlbumId === $album->id, 404);

        // 3) Hormati visibility
        $viewerId = $r->user()?->id;
        if ($album->visibility === 'private' && $viewerId !== $album->user_id) {
            abort(403);
        }

        // 4) Breakdown reaksi
        $counts = Reaction::where('asset_id', $asset->id)
            ->select('emoji', DB::raw('COUNT(*) as c'))
            ->groupBy('emoji')
            ->pluck('c', 'emoji');

        // 5) Emoji yang dipilih user (null kalau guest / belum pilih)
        $selected = $viewerId
            ? Reaction::where('asset_id', $asset->id)
            ->where('user_id', $viewerId)
            ->value('emoji')
            : null;

        // 6) Komentar + avatar user
        $comments = Comment::where('asset_id', $asset->id)
            ->where('is_hidden', false)
            ->with('user:id,name,avatar_url') // ← kirim avatar ke FE
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'body'             => $c->body,
                'user_name'        => $c->user?->name ?? 'Anon',
                'user_avatar_url'  => $c->user?->avatar_url,
                'created_at'       => $c->created_at?->toISOString(),
            ]);

        return response()->json([
            'asset_id'             => $asset->id,
            'album'                => [
                'id'         => $album->id,
                'slug'       => $album->slug,
                'title'      => $album->title ?? null,     // opsional
                'visibility' => $album->visibility,
            ],
            'reactions_breakdown'  => (object) $counts,
            'selected_emoji'       => $selected,
            'comments'             => $comments,
            'comments_count'       => $comments->count(),
        ]);
    }

    public function reactAsset(Request $r, string $slug, Asset $asset)
    {
        $user = $r->user();
        $emoji = $r->input('emoji', '❤');

        // toggle single-emoji untuk ASSET: pakai asset_id, album_id = NULL
        $row = Reaction::where('user_id', $user->id)->where('asset_id', $asset->id)->first();

        if ($row && $row->emoji === $emoji) {
            $row->delete(); // OFF
            $selected = null;
        } else {
            if ($row) {
                $row->emoji = $emoji;
                $row->save();
            } else {
                Reaction::create([
                    'user_id'  => $user->id,
                    'album_id' => null,
                    'asset_id' => $asset->id,
                    'emoji'    => $emoji,
                ]);
            }
            $selected = $emoji;
        }

        $counts = Reaction::where('asset_id', $asset->id)
            ->selectRaw('emoji, COUNT(*) as c')
            ->groupBy('emoji')
            ->pluck('c', 'emoji');

        return response()->json([
            'selected_emoji' => $selected,
            'counts'         => $counts,
        ]);
    }

    public function commentAsset(Request $r, string $slug, Asset $asset)
    {
        $user = $r->user();
        abort_if(!$user, 401);

        // 1) Ambil album by slug
        $album = Album::where('slug', $slug)->firstOrFail();

        // 2) Pastikan asset belong to album
        $assetAlbumId = $asset->page->album_id ?? $asset->album_id ?? null;
        abort_unless($assetAlbumId === $album->id, 404);

        // 3) Hormati visibility album
        if ($album->visibility === 'private' && $user->id !== $album->user_id) {
            abort(403);
        }

        // (opsional) kalau ada flag perizinan komentar:
        // if (!$album->allow_comments) abort(403, 'Komentar dimatikan.');

        // 4) Validasi & normalisasi isi
        $data = $r->validate([
            'body' => 'required|string|min:1|max:1000',
        ]);
        $body = trim($data['body']);
        if ($body === '') {
            return response()->json(['message' => 'Komentar tidak boleh kosong.'], 422);
        }

        // 5) Simpan komentar
        $c = Comment::create([
            'user_id'  => $user->id,
            'album_id' => $album->id,
            'asset_id' => $asset->id,
            'body'     => $body,
            // (opsional) metadata:
            // 'ip'         => $r->ip(),
            // 'user_agent' => Str::limit($r->userAgent() ?? '', 255),
        ]);

        // 6) Response untuk FE (sinkron dengan showAsset)
        return response()->json([
            'id'               => $c->id,
            'body'             => $c->body,
            'user_name'        => $user->name ?? 'Anon',
            'user_avatar_url'  => $user->avatar_url ?? null,
            'created_at'       => $c->created_at?->toISOString(),
        ], 201);
    }

    private function assertAlbumReadable(Request $r, Album $album)
    {
        abort_unless(in_array($album->visibility, ['public', 'unlisted']), 403);

        if ($album->password_hash) {
            $pass = $r->header('X-Album-Pass') ?? $r->input('password');
            if (!$pass || !Hash::check($pass, $album->password_hash)) {
                abort(401, 'PASSWORD_REQUIRED');
            }
        }
    }

    public function reactionsAlbum(Request $r, string $slug)
    {
        $album = Album::where('slug', $slug)->firstOrFail();
        $this->assertAlbumReadable($r, $album);

        $emoji = $r->query('emoji'); // opsional filter ?emoji=❤

        $q = Reaction::where('album_id', $album->id)
            ->when($emoji, fn($x) => $x->where('emoji', $emoji))
            ->with(['user:id,name']) // avatar_url opsional, sesuaikan kolommu
            ->orderByDesc('created_at')
            ->get(['id', 'emoji', 'user_id', 'created_at']);

        $out = [];
        foreach ($q as $row) {
            $out[$row->emoji] ??= [];
            $out[$row->emoji][] = [
                'id'           => $row->id,
                'user_id'      => $row->user_id,
                'user_name'    => $row->user->name ?? 'Anon',
                // 'user_avatar'  => $row->user->avatar_url ?? null, // kalau ada
                'created_at'   => $row->created_at,
            ];
        }

        return response()->json($out);
    }

    public function reactionsAsset(Request $r, string $slug, Asset $asset)
    {
        $album = Album::where('slug', $slug)->firstOrFail();
        $this->assertAlbumReadable($r, $album);

        // pastikan asset belong to album (sesuaikan relasi kamu)
        $assetAlbumId = $asset->page->album_id ?? ($asset->album_id ?? null);
        abort_unless($assetAlbumId === $album->id, 404);

        $emoji = $r->query('emoji'); // opsional filter ?emoji=🔥

        $q = Reaction::where('asset_id', $asset->id)
            ->when($emoji, fn($x) => $x->where('emoji', $emoji))
            ->with(['user:id,name,avatar_url'])
            ->orderByDesc('created_at')
            ->get(['id', 'emoji', 'user_id', 'created_at']);

        $out = [];
        foreach ($q as $row) {
            $out[$row->emoji] ??= [];
            $out[$row->emoji][] = [
                'id'           => $row->id,
                'user_id'      => $row->user_id,
                'user_name'    => $row->user->name ?? 'Anon',
                // 'user_avatar'  => $row->user->avatar_url ?? null,
                'created_at'   => $row->created_at,
                'user_avatar_url' => $row->user->avatar_url ?? null,
            ];
        }

        return response()->json($out);
    }
}
