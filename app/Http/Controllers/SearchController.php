<?php

namespace App\Http\Controllers;

use App\Models\Album;
use App\Models\Asset;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function index(Request $r)
    {
        $uid = $r->user()->id;
        $q = trim((string)$r->query('q', ''));
        $tags = (array)$r->query('tags', []);
        $match = $r->query('match', 'any'); // any | all
        $albumId = $r->query('album_id');
        $pageId  = $r->query('page_id');
        $limit   = min((int)$r->query('limit', 40), 100);

        // cari album dulu (judul)
        $albums = Album::where('user_id', $uid)
            ->when($q !== '', fn($qq) => $qq->where('title', 'like', "%{$q}%"))
            ->when($albumId, fn($qq) => $qq->where('id', $albumId))
            ->limit(10)->get(['id', 'title', 'slug']);

        // cari asset by caption + filter tag
        $assets = Asset::query()
            ->whereHas('page.album', fn($qq) => $qq->where('user_id', $uid))
            ->when($q !== '', fn($qq) => $qq->where('caption', 'like', "%{$q}%"))
            ->when($albumId, fn($qq) => $qq->whereHas('page', fn($q2) => $q2->where('album_id', $albumId)))
            ->when($pageId,  fn($qq) => $qq->where('page_id', $pageId))
            ->when(!empty($tags), function ($qq) use ($tags, $uid, $match) {
                $qq->whereHas('tags', function ($tq) use ($tags, $uid) {
                    $tq->where('user_id', $uid)->whereIn('name', $tags);
                }, $match === 'all' ? '>=' : '>=', $match === 'all' ? count($tags) : 1);
            })
            ->with(['tags:id,name'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'page_id', 'url', 'variants', 'caption']);

        return compact('albums', 'assets');
    }
}
