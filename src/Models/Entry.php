<?php

namespace IlBronza\MediaManager\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Entry extends Model
{
    use HasUuids;

    protected $table = 'media_manager_entries';
    protected $guarded = [];
    protected $casts = ['metadata' => 'array', 'root_identity' => 'array', 'spatie_paths' => 'array', 'identity_tagged' => 'boolean', 'size' => 'integer', 'last_seen_at' => 'datetime'];

    protected static function booted(): void
    {
        static::saving(function (self $entry) { $entry->path_hash = $entry->status === 'superseded' ? null : hash('sha256', $entry->path); });
        static::saved(function (self $entry) {
            if ($entry->spatie_media_id && config('media-manager.spatie')) app(\IlBronza\MediaManager\Integrations\Spatie\MediaLocation::class)->sync($entry);
        });
    }

    public function parent(): BelongsTo { return $this->belongsTo(self::class, 'parent_id'); }
    public function children(): HasMany { return $this->hasMany(self::class, 'parent_id'); }
    public function links(): HasMany { return $this->hasMany(MediaLink::class, 'entry_id'); }
    public function getNameAttribute(): string { return $this->path === '' ? $this->disk : basename($this->path); }
    public function isDirectory(): bool { return $this->kind === 'directory'; }
}
