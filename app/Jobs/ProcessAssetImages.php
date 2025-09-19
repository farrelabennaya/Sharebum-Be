<?php

namespace App\Jobs;

use App\Models\Asset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable; // <- yang benar
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Facades\Http;

class ProcessAssetImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $assetId) {}

    public function handle(): void
    {
        $asset = Asset::find($this->assetId);
        if (!$asset || $asset->type !== 'photo') return;

        $disk = Storage::disk('s3');

        // download original ke file sementara (biar hemat memory)
        $tmp = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($tmp, $disk->get($asset->storage_key));

        $sizes = [
            'thumb' => 320,
            'md'    => 1080,
            'lg'    => 1920,
        ];
        $variants = [];

        foreach ($sizes as $label => $maxW) {
            $img = Image::read($tmp);

            // Hindari upscaling: pakai lebar minimum
            $targetW = min($maxW, $img->width());
            $img = $img->scale(width: $targetW);

            $variantKey = str_replace('.', "_{$label}.", $asset->storage_key);

            // Encode berdasarkan ekstensi (jpg/png/webp) lalu upload ke R2
            $binary = (string) $img->encodeByPath($variantKey);

            // ❌ jangan set 'visibility' untuk R2
            $disk->put($variantKey, $binary);

            // Bangun URL publik dari AWS_URL (sudah kamu set)
            $publicBase = rtrim(config('filesystems.disks.s3.url'), '/');
            $variants[$label] = $publicBase . '/' . ltrim($variantKey, '/');
        }

        // Ukuran asli (kalau belum ada)
        $imgInfo = Image::read($tmp);
        @unlink($tmp);

        $asset->variants = $variants;
        $asset->width ??= $imgInfo->width();
        $asset->height ??= $imgInfo->height();
        $asset->save();

        // Revalidate Next (opsional)
        if ($asset->page->album->is_public && env('NEXT_REVALIDATE_URL')) {
            Http::post(env('NEXT_REVALIDATE_URL'), [
                'secret' => env('NEXT_REVALIDATE_SECRET'),
                'path'   => "/album/{$asset->page->album->slug}",
            ])->throw();
        }
    }
}
