<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('idol_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique()->nullable(); // boleh dibuat belakangan
            $table->enum('visibility', ['private','unlisted','public'])->default('private');
            $table->string('password_hash')->nullable();

            $table->string('cover_url')->nullable();
            $table->json('theme')->nullable();
            $table->json('palette')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};
