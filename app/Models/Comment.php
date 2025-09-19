<?php

// app/Models/Comment.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    protected $fillable = ['user_id','album_id','asset_id','body','is_hidden'];

    public function album() { return $this->belongsTo(Album::class); }
    public function asset() { return $this->belongsTo(Asset::class); }
    public function user()  { return $this->belongsTo(User::class); }
}
