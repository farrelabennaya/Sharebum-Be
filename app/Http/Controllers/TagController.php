<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Asset;
use Illuminate\Http\Request;

class TagController extends Controller
{
    public function index(Request $r, Asset $asset)
    {
        abort_unless($asset->page->album->user_id === $r->user()->id, 403);
        return $asset->tags()->orderBy('name')->get(['id', 'name']);
    }

    public function attach(Request $r, Asset $asset)
    {
        abort_unless($asset->page->album->user_id === $r->user()->id, 403);
        $data = $r->validate(['tags' => 'required|array', 'tags.*' => 'string|max:50']);
        $userId = $r->user()->id;

        $ids = collect($data['tags'])
            ->map(fn($n) => trim($n))
            ->filter()
            ->unique()
            ->map(function ($name) use ($userId) {
                return Tag::firstOrCreate(['user_id' => $userId, 'name' => $name])->id;
            })->all();

        $asset->tags()->syncWithoutDetaching($ids);
        return $asset->tags()->get(['id', 'name']);
    }

    public function detach(Request $r, Asset $asset, Tag $tag)
    {
        abort_unless($asset->page->album->user_id === $r->user()->id && $tag->user_id === $r->user()->id, 403);
        $asset->tags()->detach($tag->id);
        return response()->noContent();
    }

    // list semua tag milik user + count dipakai filter
    public function catalog(Request $r)
    {
        $uid = $r->user()->id;
        $tags = Tag::where('user_id', $uid)
            ->select('tags.id', 'tags.name')
            ->withCount(['assets as count' => function ($q) use ($uid) {
                $q->whereHas('page.album', fn($qq) => $qq->where('user_id', $uid));
            }])
            ->orderByDesc('count')->orderBy('name')
            ->get();
        return $tags;
    }
}
