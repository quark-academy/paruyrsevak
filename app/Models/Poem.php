<?php

namespace App\Models;

use App\Support\PoemText;
use Database\Factories\PoemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Poem extends Model
{
    /** @use HasFactory<PoemFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'slug',
        'content',
        'audio_path',
        'is_original',
        'meta_title',
        'meta_description',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_original' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Poem $poem): void {
            $poem->slug = filled($poem->slug)
                ? Str::slug((string) $poem->slug)
                : static::makeUniqueSlug((string) $poem->title, $poem->getKey());

            if (blank($poem->slug)) {
                $poem->slug = 'poem-'.Str::lower(Str::random(8));
            }
        });
    }

    /**
     * Words carrying karaoke word-by-word timings ordered by appearance.
     *
     * @return HasMany<PoemWord, $this>
     */
    public function words(): HasMany
    {
        return $this->hasMany(PoemWord::class)->orderBy('word_order');
    }

    /**
     * Bind poems to routes through their slug instead of the id.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Build a unique, ASCII (transliterated) slug for the given title.
     */
    public static function makeUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title);
        $base = $base !== '' ? $base : 'poem';
        $slug = $base;
        $counter = 1;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.(++$counter);
        }

        return $slug;
    }

    /**
     * Whether the poem carries a real text (not the importer's placeholder).
     */
    public function hasRealText(): bool
    {
        return PoemText::looksReal($this->content);
    }

    /**
     * Determine whether an audio file is available on disk.
     */
    public function hasAudio(): bool
    {
        return filled($this->audio_path)
            && Storage::disk('public')->exists((string) $this->audio_path);
    }

    /**
     * Public URL of the active audio (archive recording or AI voice).
     */
    public function audioUrl(): ?string
    {
        return filled($this->audio_path)
            ? Storage::disk('public')->url((string) $this->audio_path)
            : null;
    }

    /**
     * Human readable source of the current voice track.
     */
    public function voiceSourceLabel(): string
    {
        return $this->is_original ? 'Արխիվային ձայն' : 'ԱԻ ձայն';
    }

    /**
     * Short plain-text excerpt used in listings and meta description.
     */
    public function excerpt(int $limit = 140): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', (string) $this->content) ?? ''), $limit);
    }

    protected static function newFactory(): PoemFactory
    {
        return PoemFactory::new();
    }
}
