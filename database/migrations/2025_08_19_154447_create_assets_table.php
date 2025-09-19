<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['photo', 'video'])->default('photo');
            $table->string('storage_key');        // e.g. albums/{album}/{uuid}.jpg
            $table->string('url');                // CDN URL
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->text('caption')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->json('variants')->nullable(); // thumb/md/lg
            $table->json('palette')->nullable();  // dominant colors
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
