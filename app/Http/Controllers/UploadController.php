<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\{Album, Page, Asset};
use App\Jobs\ProcessAssetImages;

class UploadController extends Controller
{
    public function presign(Request $r)
    {
        $data = $r->validate([
            'album_id' => 'required|exists:albums,id',
            'mime'     => 'required|string', // image/jpeg, image/png, image/webp
            'ext'      => 'required|string', // jpg, png, webp
        ]);

        $album = \App\Models\Album::findOrFail($data['album_id']);
        abort_unless($album->user_id === $r->user()->id, 403);

        $key = "albums/{$album->id}/" . (string) Str::uuid() . '.' . $data['ext'];

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        // ✅ Presigned PUT — pakai temporaryUploadUrl kalau tersedia
        if (method_exists($disk, 'temporaryUploadUrl')) {
            $url = $disk->temporaryUploadUrl($key, now()->addMinutes(10), [
                // penting: header ini HARUS sama saat melakukan PUT
                'ContentType' => $data['mime'],
                // 'ACL' => 'public-read', // biasanya tak perlu di R2 (public bucket diatur di dashboard)
            ]);
        } else {
            // ♻️ Fallback via AWS SDK (PutObject)
            $client = $disk->getClient(); // \Aws\S3\S3Client
            $cmd = $client->getCommand('PutObject', [
                'Bucket'      => config('filesystems.disks.s3.bucket'),
                'Key'         => $key,
                'ContentType' => $data['mime'],
            ]);
            $request = $client->createPresignedRequest($cmd, '+10 minutes');
            $url = (string) $request->getUri();
        }

        return [
            'key'     => $key,
            'method'  => 'PUT',
            'url'     => $url,
            'headers' => ['Content-Type' => $data['mime']],
        ];
    }

    public function finalize(Request $r)
    {
        $data = $r->validate([
            'page_id' => 'required|exists:pages,id',
            'key'     => 'required|string',
            'width'   => 'nullable|integer',
            'height'  => 'nullable|integer',
            'caption' => 'nullable|string',
            'order'   => 'nullable|integer',
            'type'    => 'nullable|in:photo,video',
        ]);

        $page = \App\Models\Page::with('album')->findOrFail($data['page_id']);
        abort_unless($page->album->user_id === $r->user()->id, 403);

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        // ✅ URL publik (pakai AWS_URL / public domain R2)
        $configuredPublicBase = config('filesystems.disks.s3.url'); // dari AWS_URL
        $url = $configuredPublicBase
            ? rtrim($configuredPublicBase, '/') . '/' . ltrim($data['key'], '/')
            : $disk->url($data['key']); // fallback (akan pakai endpoint S3 kalau AWS_URL kosong)

        $asset = \App\Models\Asset::create([
            'page_id'     => $page->id,
            'type'        => $data['type'] ?? 'photo',
            'storage_key' => $data['key'],
            'url'         => $url,
            'width'       => $data['width'] ?? null,
            'height'      => $data['height'] ?? null,
            'caption'     => $data['caption'] ?? null,
            'order'       => $data['order'] ?? 0,
        ]);

        dispatch(new ProcessAssetImages($asset->id));

        return $asset->fresh();
    }
}
