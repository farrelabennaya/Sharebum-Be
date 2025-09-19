<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_profile_fields_to_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_url')->nullable()->after('email');
            // opsional lain:
            // $table->string('username')->unique()->nullable()->after('name');
            // $table->text('bio')->nullable()->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar_url' /*,'username','bio'*/]);
        });
    }
};
