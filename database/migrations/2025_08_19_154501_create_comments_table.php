<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // COMMENTS
        Schema::create('comments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('album_id')->constrained()->cascadeOnDelete();
            $t->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete(); // null = komentar untuk album
            $t->text('body');
            $t->boolean('is_hidden')->default(false);
            $t->timestamps();

            // index untuk listing cepat
            $t->index(['album_id', 'created_at']);
            $t->index(['asset_id', 'created_at']);
        });

        // REACTIONS — single-emoji per target (album ATAU asset)
        Schema::create('reactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Penting: salah satu diisi
            $t->foreignId('album_id')->nullable()->constrained()->cascadeOnDelete(); // isi untuk reaksi album
            $t->foreignId('asset_id')->nullable()->constrained()->cascadeOnDelete(); // isi untuk reaksi asset

            $t->string('emoji', 16); // ❤ 👍 😂 ✨
            $t->timestamps();

            // Unik per target:
            $t->unique(['user_id', 'album_id']); // berlaku untuk baris album (asset_id = NULL)
            $t->unique(['user_id', 'asset_id']); // berlaku untuk baris asset (album_id boleh NULL)

            // hitung cepat:
            $t->index(['album_id', 'emoji']);
            $t->index(['asset_id', 'emoji']);
        });

        // (opsional) CHECK constraint (MySQL 8+)
        // DB::statement('ALTER TABLE reactions ADD CONSTRAINT chk_target_oneof CHECK (
        //   (album_id IS NULL) <> (asset_id IS NULL)
        // )');
    }

    public function down(): void
    {
        Schema::dropIfExists('reactions');
        Schema::dropIfExists('comments');
    }
};
