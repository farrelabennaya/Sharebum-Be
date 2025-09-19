<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    /** @use HasFactory<\Database\Factories\AssetFactory> */
    use HasFactory;

    protected $fillable = ['page_id', 'type', 'storage_key', 'url', 'width', 'height', 'caption', 'order', 'variants', 'palette'];
    protected $casts = ['variants' => 'array', 'palette' => 'array'];
    public function page()
    {
        return $this->belongsTo(Page::class);
    }
    public function tags()
    {
        return $this->belongsToMany(Tag::class);
    }
}
