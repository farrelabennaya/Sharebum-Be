<?php

namespace App\Http\Controllers;

use App\Models\{Asset, Page, Album};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssetController extends Controller
{
    /** Tambah suffix sebelum ekstensi terakhir (…/foo/bar.jpg -> …/foo/bar_thumb.jpg) */
    private function variantKey(string $key, string $label): string
    {
        // ganti tepat di ekstensi terakhir, aman walau path mengandung titik
        return preg_replace('/(\.[^\/.]+)$/', "_{$label}$1", $key);
    }

    // PATCH /api/assets/{asset}
    public function update(Request $r, Asset $asset)
    {
        abort_unless($asset->page->album->user_id === $r->user()->id, 403);

        $data = $r->validate([
            'caption' => 'sometimes|string|max:300',
            'order'   => 'sometimes|integer|min:0',
        ]);

        $asset->update($data);
        return $asset->fresh();
    }

    // POST /api/assets/bulk-delete  body: { ids: [1,2,3] }
    public function bulkDelete(Request $r)
    {
        $payload = $r->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer'
        ]);

        $assets = Asset::with('page.album')
            ->whereIn('id', $payload['ids'])
            ->get()
            ->filter(fn($a) => $a->page->album->user_id === $r->user()->id);

        $disk = Storage::disk('s3');

        foreach ($assets as $a) {
            // hapus file asli + varian (jika ada)
            $keys = [$a->storage_key];

            // jika varian mengikuti pola "file_thumb.ext"
            if (is_array($a->variants)) {
                foreach ($a->variants as $label => $url) {
                    // derive key dari storage_key
                    $variantKey = str_replace('.', "_{$label}.", $a->storage_key);
                    $keys[] = $variantKey;
                }
            } else {
                // fallback: hapus suffix umum
                foreach (['thumb', 'md', 'lg'] as $label) {
                    $keys[] = str_replace('.', "_{$label}.", $a->storage_key);
                }
            }

            try {
                $disk->delete($keys);
            } catch (\Throwable $e) {
            }
            $a->delete();
        }

        return ['deleted' => $assets->pluck('id')->values()];
    }

    // PATCH /api/pages/{page}/assets/reorder  body: { order: { "assetId": index, ... } }
    public function reorder(Request $r, Page $page)
    {
        abort_unless($page->album->user_id === $r->user()->id, 403);

        $payload = $r->validate([
            'order' => 'required|array'
        ]);

        foreach ($payload['order'] as $assetId => $index) {
            Asset::where('page_id', $page->id)
                ->where('id', $assetId)
                ->update(['order' => (int)$index]);
        }

        return ['ok' => true];
    }

    // DELETE /api/assets/{asset}
    public function destroy(Request $r, Asset $asset)
    {
        abort_unless($asset->page->album->user_id === $r->user()->id, 403);

        $disk = Storage::disk('s3');

        // kumpulkan kunci original + varian
        $keys = [$asset->storage_key];
        foreach (['thumb', 'md', 'lg'] as $v) {
            $keys[] = $this->variantKey($asset->storage_key, $v);
        }

        // move ke trash/ (driver S3 => copy + delete)
        foreach ($keys as $k) {
            $disk->move($k, "trash/{$k}");
        }

        // hapus record DB (atau soft delete kalau kamu pakai)
        $asset->delete();

        return response()->noContent();
    }
}
