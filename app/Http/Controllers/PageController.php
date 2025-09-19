<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Album;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

// app/Http/Controllers/PageController.php
class PageController extends Controller
{
    public function store(Request $r, Album $album)
    {
        abort_unless($album->user_id === $r->user()->id, 403);

        $data = $r->validate([
            'layout_type' => 'sometimes|string',   // ⬅️ tidak wajib
            'bg_texture'  => 'nullable|string',
            'notes'       => 'nullable|string',
            'index'       => 'nullable|integer'
        ]);

        $max = (int) ($album->pages()->max('index') ?? -1); // default -1 biar page pertama = 0
        $index = array_key_exists('index', $data) ? (int)$data['index'] : $max + 1;

        $page = $album->pages()->create([
            'layout_type' => $data['layout_type'] ?? 'grid', // ⬅️ default
            'bg_texture'  => $data['bg_texture']  ?? null,
            'notes'       => $data['notes']       ?? null,
            'index'       => $index,
        ]);

        return $page->fresh();
    }

    public function update(Request $r, Page $page)
    {
        abort_unless($page->album->user_id === $r->user()->id, 403);
        $data = $r->validate([
            'layout_type' => 'sometimes|string',
            'bg_texture' => 'sometimes|string',
            'notes' => 'sometimes|string',
        ]);
        $page->update($data);
        return $page->fresh();
    }

    private function variantKey(string $key, string $label): string
    {
        // sisipkan _{label} sebelum ekstensi terakhir
        return preg_replace('/(\.[^\/.]+)$/', "_{$label}$1", $key);
    }

    public function destroy(Request $r, Page $page)
    {
        // hanya owner
        abort_unless($page->album->user_id === $r->user()->id, 403);

        // pindahkan semua asset (original + varian) ke trash/
        $disk = Storage::disk('s3');
        $page->load('assets');

        foreach ($page->assets as $asset) {
            $keys = [
                $asset->storage_key,
                $this->variantKey($asset->storage_key, 'thumb'),
                $this->variantKey($asset->storage_key, 'md'),
                $this->variantKey($asset->storage_key, 'lg'),
            ];
            foreach ($keys as $k) {
                if ($disk->exists($k)) {
                    $disk->move($k, "trash/{$k}");
                }
            }
        }

        // hapus page → pastikan FK assets cascadeOnDelete di migration
        $page->delete();

        // (opsional) rapikan index halaman yg tersisa
        $pages = $page->album->pages()->orderBy('index')->get();
        foreach ($pages as $i => $p) {
            $p->update(['index' => $i]);
        }

        return response()->noContent();
    }

    public function reorder(Request $r, Album $album)
    {
        abort_unless($album->user_id === $r->user()->id, 403);
        $payload = $r->validate([
            'order' => 'required|array', // [pageId => index, ...]
        ]);
        foreach ($payload['order'] as $pageId => $index) {
            Page::where('album_id', $album->id)->where('id', $pageId)->update(['index' => $index]);
        }
        return ['ok' => true];
    }
}
