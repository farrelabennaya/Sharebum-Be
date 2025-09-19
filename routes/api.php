<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TagController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AlbumController;
use App\Http\Controllers\AssetController;
use App\Http\Controllers\ShareController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReactionController;
use App\Http\Controllers\AssetSocialController;
use App\Http\Controllers\PublicAlbumController;
use App\Http\Controllers\ProfileAvatarController;
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);
Route::post('/auth/logout',   [AuthController::class, 'logout'])->middleware('auth:sanctum');

// TAMBAHAN:
Route::post('/auth/google',   [AuthController::class, 'google']);


Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/public/albums/{slug}', [PublicAlbumController::class, 'show']); // untuk Next (no auth)

Route::get('/public/albums/{slug}/reactions', [PublicAlbumController::class, 'reactionsAlbum']);
Route::get('/public/albums/{slug}/assets/{asset}/reactions', [PublicAlbumController::class, 'reactionsAsset']);


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/albums', [AlbumController::class, 'index']);
    Route::post('/albums', [AlbumController::class, 'store']);
    Route::get('/albums/{id}', [AlbumController::class, 'show']);   // owner only
    Route::patch('/albums/{id}', [AlbumController::class, 'update']);
    Route::delete('/albums/{id}', [AlbumController::class, 'destroy']);

    Route::post('/albums/{album}/pages', [PageController::class, 'store']);
    Route::patch('/pages/{page}', [PageController::class, 'update']);
    Route::patch('/pages/{album}/reorder', [PageController::class, 'reorder']);
    Route::delete('/pages/{page}', [PageController::class, 'destroy']);


    // Upload: presign + finalize
    Route::post('/uploads/presign', [UploadController::class, 'presign']);
    Route::post('/uploads/finalize', [UploadController::class, 'finalize']);

    Route::delete('/assets/{asset}', [AssetController::class, 'destroy']);
    Route::patch('/assets/{asset}', [AssetController::class, 'update']);                  // edit caption/order
    Route::post('/assets/bulk-delete', [AssetController::class, 'bulkDelete']);          // hapus massal (body JSON)
    Route::patch('/pages/{page}/assets/reorder', [AssetController::class, 'reorder']);   // reorder dalam 1 page

    // Album cover
    Route::post('/albums/{album}/cover', [AlbumController::class, 'setCover']);          // pilih cover by asset_id

    // Tagging per-asset
    Route::get('/assets/{asset}/tags', [TagController::class, 'index']);          // list tags asset
    Route::post('/assets/{asset}/tags', [TagController::class, 'attach']);        // body: { tags: ["concert","2024"] }
    Route::delete('/assets/{asset}/tags/{tag}', [TagController::class, 'detach']); // lepas 1 tag

    // Katalog tag user (untuk filter chips)
    Route::get('/tags', [TagController::class, 'catalog']); // ?q=con (optional like), balikin {name,count}

    // Search (judul album & caption foto) + filter
    Route::get('/search', [SearchController::class, 'index']);
    // params: q, tags[]=, match=any|all (default any), album_id, page_id, limit

    Route::get('/album/{slug}', [ShareController::class, 'page']);

    Route::post('/albums/{album}/react', [ReactionController::class, 'toggle']);
    Route::post('/public/albums/{slug}/react', [PublicAlbumController::class, 'react']);
    Route::post('/public/{albums}/react', [PublicAlbumController::class, 'react']);
    Route::post('/public/albums/{slug}/comments', [CommentController::class, 'store']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);

    // Route::post('/public/albums/{slug}/assets/{asset}/react', [PublicAlbumController::class, 'reactAlbum']);
    // Route::post('/public/albums/{slug}/assets/{asset}/comments', [PublicAlbumController::class, 'commentAlbum']);

    Route::post('/public/albums/{slug}/assets/{asset}/react', [PublicAlbumController::class, 'reactAsset']);
    Route::post('/public/albums/{slug}/assets/{asset}/comments', [PublicAlbumController::class, 'commentAsset']);


    Route::get('/assets/{asset}/social', [AssetSocialController::class, 'show']);         // counts + selected + comments
    Route::post('/assets/{asset}/react', [AssetSocialController::class, 'react']);        // toggle/react (single-emoji)
    Route::post('/assets/{asset}/comments', [AssetSocialController::class, 'comment']);   // tambah komentar
    Route::get('/assets/{asset}/reactions', [AssetSocialController::class, 'reactions']); // { "❤": [ {user_id,...} ], ... }

     // Profil dasar
    Route::get('/me', [ProfileController::class, 'show']);
    Route::patch('/me', [ProfileController::class, 'update']);

    // Avatar
    Route::post('/me/avatar', [ProfileAvatarController::class, 'store']);
    Route::delete('/me/avatar', [ProfileAvatarController::class, 'destroy']);

});

Route::middleware('auth:sanctum')->get('/me', function (Request $request) {
    return response()->json($request->user());
});

// Asset-level (di halaman public/lightbox)
Route::get('/public/albums/{slug}/assets/{asset}', [PublicAlbumController::class, 'showAsset']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/public/albums/{slug}/assets/{asset}/react', [PublicAlbumController::class, 'reactAsset']);
    Route::post('/public/albums/{slug}/assets/{asset}/comments', [PublicAlbumController::class, 'commentAsset']);
});
