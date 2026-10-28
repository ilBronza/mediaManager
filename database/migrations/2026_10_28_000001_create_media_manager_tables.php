<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('media_manager_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('disk', 100);
            $table->text('path');
            $table->char('path_hash', 64);
            $table->unique(['disk', 'path_hash']);
            $table->uuid('parent_id')->nullable()->index();
            $table->string('kind', 16);
            $table->string('status', 24)->default('present');
            $table->char('sha256', 64)->nullable()->index();
            $table->unsignedBigInteger('size')->nullable();
            $table->boolean('identity_tagged')->default(false);
            $table->json('metadata')->nullable();
            $table->json('root_identity')->nullable();
            $table->string('spatie_media_id')->nullable()->unique();
            $table->json('spatie_paths')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
        });
        Schema::create('media_manager_links', function (Blueprint $table) {
            $table->id();
            $table->uuid('entry_id');
            $table->foreign('entry_id')->references('id')->on('media_manager_entries')->cascadeOnDelete();
            $table->string('linkable_type', 191);
            $table->string('linkable_id', 191);
            $table->string('role', 80)->default('attachment');
            $table->index(['linkable_type', 'linkable_id']);
            $table->unique(['entry_id', 'linkable_type', 'linkable_id', 'role'], 'media_manager_link_unique');
            $table->timestamps();
        });
        Schema::create('media_manager_operations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('entry_id')->index();
            $table->string('disk', 100);
            $table->text('source');
            $table->text('destination');
            $table->string('state', 24)->default('prepared');
            $table->json('snapshot');
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_manager_operations');
        Schema::dropIfExists('media_manager_links');
        Schema::dropIfExists('media_manager_entries');
    }
};
