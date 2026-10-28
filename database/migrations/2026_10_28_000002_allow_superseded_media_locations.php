<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        // Historical/soft-deleted media retain their last path without claiming its
        // unique active slot after File Cabinet writes a replacement there.
        Schema::table('media_manager_entries', fn (Blueprint $table) => $table->char('path_hash', 64)->nullable()->change());
    }

    public function down(): void
    {
        if (DB::table('media_manager_entries')->whereNull('path_hash')->exists()) throw new RuntimeException('Resolve superseded catalogue entries before rolling back this migration.');
        Schema::table('media_manager_entries', fn (Blueprint $table) => $table->char('path_hash', 64)->nullable(false)->change());
    }
};
