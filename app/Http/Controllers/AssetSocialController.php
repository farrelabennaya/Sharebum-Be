<?php

namespace App\Http\Controllers;

use App\Models\{Asset, Reaction, Comment};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetSocialController extends Controller
{
    /**
     * Ringkasan sosial 1 asset:
     * - reactions_breakdown: { "❤": 3, "🔥": 2, ... }
     * - selected_emoji: emoji yang dipilih user (atau null)
     * - comments: [{ id, body, user_name, created_at }]
     */
    public function show(Request $r, Asset $asset)
    {
        $user   = $r->user();
        $userId = $user?->id;

        // breakdown reaksi per emoji
        $breakdown = Reaction::where('asset_id', $asset->id)
            ->select('emoji', DB::raw('COUNT(*) as c'))
            ->groupBy('emoji')
            ->pluck('c', 'emoji');

        // emoji yang dipilih user login (null kalau guest / belum pilih)
        $selected = $userId
            ? Reaction::where('asset_id', $asset->id)
            ->where('user_id', $userId)
            ->value('emoji')
            : null;

        // daftar komentar + avatar user
        $comments = Comment::where('asset_id', $asset->id)
            ->where('is_hidden', false)
            ->with('user:id,name,avatar_url') // ← tambahkan avatar_url
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn($c) => [
                'id'               => $c->id,
                'body'             => $c->body,
                'user_name'        => $c->user?->name ?? 'Anon',
                'user_avatar_url'  => $c->user?->avatar_url, // ← kirim ke FE
                'created_at'       => $c->created_at?->toISOString(),
            ]);

        return response()->json([
            'asset_id'            => $asset->id,
            'reactions_breakdown' => (object) $breakdown,
            'selected_emoji'      => $selected,
            'comments'            => $comments,
            'comments_count'      => $comments->count(), // opsional
        ]);
    }

    /**
     * Daftar reaksi, dikelompokkan per emoji:
     * { "❤": [ {id,user_id,user_name,created_at}, ... ], "🔥": [...] }
     * Optional filter: ?emoji=❤
     */
    public function reactions(Request $r, Asset $asset)
    {
        $emoji = $r->query('emoji');

        $rows = Reaction::where('asset_id', $asset->id)
            ->when($emoji, fn($q) => $q->where('emoji', $emoji))
            ->with('user:id,name,avatar_url')
            ->orderByDesc('created_at')
            ->get(['id', 'emoji', 'user_id', 'created_at']);

        $out = [];
        foreach ($rows as $row) {
            $out[$row->emoji] ??= [];
            $out[$row->emoji][] = [
                'id'          => $row->id,
                'user_id'     => $row->user_id,
                'user_name'   => $row->user->name ?? 'Anon',
                'user_avatar_url' => $row->user->avatar_url ?? null, // kalau ada kolomnya
                'created_at'  => $row->created_at,
            ];
        }

        return response()->json($out);
    }

    /**
     * Toggle reaction user pada asset.
     * Body: { emoji: "❤" }
     * Balikkan: { selected_emoji, counts }
     */
    public function react(Request $r, Asset $asset)
    {
        $r->validate(['emoji' => 'nullable|string|max:8']);
        $user  = $r->user();
        $emoji = $r->input('emoji'); // null = hapus

        $current = Reaction::where('asset_id', $asset->id)
            ->where('user_id', $user->id)
            ->first();

        if ($current && $current->emoji === $emoji) {
            // klik emoji yang sama → OFF
            $current->delete();
            $selected = null;
        } else {
            // single-emoji: pastikan satu baris saja per user-asset
            if ($current) $current->delete();
            if ($emoji) {
                Reaction::create([
                    'user_id'  => $user->id,
                    'album_id' => null,        // di level asset
                    'asset_id' => $asset->id,
                    'emoji'    => $emoji,
                ]);
            }
            $selected = $emoji ?: null;
        }

        $counts = Reaction::where('asset_id', $asset->id)
            ->select('emoji', DB::raw('COUNT(*) as c'))
            ->groupBy('emoji')
            ->pluck('c', 'emoji');

        return response()->json([
            'selected_emoji' => $selected,
            'counts'         => $counts,
        ]);
    }

    /**
     * Kirim komentar ke asset.
     * Body: { body: "..." }
     * Balikkan: { id, body, user_name, created_at }
     */
    public function comment(Request $r, Asset $asset)
    {
        // validasi
        $data = $r->validate([
            'body' => 'required|string|min:1|max:1000',
        ]);

        $u = $r->user();

        // temukan album-nya dari asset (via page->album atau fallback field langsung)
        $album = $asset->page->album ?? $asset->album ?? null;
        abort_unless($album, 404);

        // aturan akses sederhana (optional, sesuaikan kebutuhanmu)
        if ($album->visibility === 'private' && $album->user_id !== $u->id) {
            abort(403);
        }
        // kalau ada flag izin komentar, contoh:
        // if (!$album->allow_comments) abort(403, 'Komentar dimatikan untuk album ini.');

        // simpan komentar
        $c = Comment::create([
            'user_id'  => $u->id,
            'album_id' => $album->id,          // untuk reporting/filter
            'asset_id' => $asset->id,
            'body'     => $data['body'],
        ]);

        // (opsional) eager load relasi user biar aman di response
        $c->load('user:id,name,avatar_url');

        return response()->json([
            'id'               => $c->id,
            'body'             => $c->body,
            'user_name'        => $c->user?->name ?? 'Anon',
            'user_avatar_url'  => $c->user?->avatar_url,   // ← avatar dari DB
            'created_at'       => $c->created_at->toISOString(),
        ], 201);
    }
}
