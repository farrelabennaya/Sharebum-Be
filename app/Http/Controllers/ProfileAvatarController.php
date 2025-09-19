<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\File;

class ProfileAvatarController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'avatar' => [ 'required', File::image()->max(2 * 1024) ], // 2MB
        ], [
            'avatar.required' => 'File avatar wajib diunggah.',
            'avatar.image'    => 'File harus berupa gambar.',
            'avatar.max'      => 'Ukuran maksimal 2MB.',
        ]);

        $user = $request->user();
        $disk = Storage::disk('s3'); // ← sama dengan AssetController (R2)

        // Hapus avatar lama (kalau ada) di R2 (fallback: lokal /storage)
        if ($user->avatar_url) {
            $publicBase = rtrim(config('filesystems.disks.s3.url') ?? env('R2_PUBLIC_URL', ''), '/') . '/';
            $oldKey = Str::after($user->avatar_url, $publicBase);

            if ($oldKey !== $user->avatar_url && $oldKey) {
                // lama di R2
                try { $disk->delete($oldKey); } catch (\Throwable $e) {}
            } else {
                // lama di lokal (/storage)
                $rel = Str::after($user->avatar_url, '/storage/');
                if ($rel !== $user->avatar_url && $rel) {
                    try { Storage::disk('public')->delete($rel); } catch (\Throwable $e) {}
                }
            }
        }

        // Simpan avatar baru ke R2, di path: avatars/{user_id}/<uuid>.ext
        $file = $request->file('avatar');
        $ext  = $file->getClientOriginalExtension() ?: 'jpg';
        $name = (string) Str::uuid() . '.' . $ext;
        $key  = "avatars/{$user->id}/{$name}";

        $disk->putFileAs("avatars/{$user->id}", $file, $name, [
            'visibility'   => 'public',
            'CacheControl' => 'public, max-age=31536000, immutable',
            'ContentType'  => $file->getMimeType(),
        ]);

        // URL publik dari disk R2 (jangan pakai url(Storage::url(...)))
        $publicUrl = $disk->url($key);

        $user->avatar_url = $publicUrl;
        $user->save();

        return response()->json([
            'message'    => 'Avatar diperbarui.',
            'avatar_url' => $publicUrl,
        ], 201);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        if (!$user->avatar_url) {
            return response()->json(['message' => 'Tidak ada avatar.']);
        }

        $disk = Storage::disk('s3');

        $publicBase = rtrim(config('filesystems.disks.s3.url') ?? env('R2_PUBLIC_URL', ''), '/') . '/';
        $oldKey = Str::after($user->avatar_url, $publicBase);

        if ($oldKey !== $user->avatar_url && $oldKey) {
            try { $disk->delete($oldKey); } catch (\Throwable $e) {}
        } else {
            $rel = Str::after($user->avatar_url, '/storage/');
            if ($rel !== $user->avatar_url && $rel) {
                try { Storage::disk('public')->delete($rel); } catch (\Throwable $e) {}
            }
        }

        $user->avatar_url = null;
        $user->save();

        return response()->json(['message' => 'Avatar dihapus.']);
    }
}
