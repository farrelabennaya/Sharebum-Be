<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    /** @use HasFactory<\Database\Factories\PageFactory> */
    use HasFactory;

    protected $fillable = ['album_id', 'index', 'layout_type', 'bg_texture', 'notes'];
    public function album()
    {
        return $this->belongsTo(Album::class);
    }
    public function assets()
    {
        return $this->hasMany(Asset::class)->orderBy('order');
    }
}
