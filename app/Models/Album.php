<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Album extends Model
{
    /** @use HasFactory<\Database\Factories\AlbumFactory> */
    use HasFactory;

    protected $fillable = ['user_id', 'idol_id', 'title', 'slug', 'is_public', 'cover_url', 'theme', 'palette', 'visibility', 'password_hash',];
    protected $casts = ['theme' => 'array', 'palette' => 'array', 'is_public' => 'boolean', 'visibility' => 'string'];

    // sembunyikan hash di response
    protected $hidden = ['password_hash'];

    // kirim field turunan 'password_protected'
    protected $appends = ['password_protected'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function idol()
    {
        return $this->belongsTo(Idol::class);
    }
    public function pages()
    {
        return $this->hasMany(Page::class)->orderBy('index');
    }
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }
    public function reactions()
    {
        return $this->hasMany(Reaction::class);
    }

    public function getPasswordProtectedAttribute(): bool
    {
        return !empty($this->password_hash);
    }
}
