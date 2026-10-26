<?php

namespace App\Models;

use App\Support\PoemText;
use App\Support\PoemThemes;
use Database\Factories\PoemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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
        'themes',
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
            'themes' => 'array',
            'is_original' => 'boolean',
            'plays_count' => 'integer',
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

        // A new poem always starts with at least one theme; editors can still
        // change or clear it later (the saving hook above leaves them alone).
        static::creating(function (Poem $poem): void {
            if (blank($poem->themes)) {
                $poem->themes = PoemThemes::forTitle((string) $poem->title);
            }
        });
    }

    /**
     * Theme keys of the poem, in the canonical order.
     *
     * @return list<string>
     */
    public function themeKeys(): array
    {
        return PoemThemes::normalize((array) ($this->themes ?? []));
    }

    /**
     * Armenian labels of the poem's themes.
     *
     * @return list<string>
     */
    public function themeLabels(): array
    {
        return PoemThemes::labelsFor($this->themeKeys());
    }

    public function hasTheme(string $theme): bool
    {
        return in_array($theme, $this->themeKeys(), true);
    }

    /**
     * Filter the catalogue by a single theme key.
     *
     * @param  Builder<Poem>  $query
     * @return Builder<Poem>
     */
    public function scopeWithTheme(Builder $query, string $theme): Builder
    {
        if (! array_key_exists($theme, PoemThemes::LABELS)) {
            return $query;
        }

        return $query->where('themes', 'like', '%"'.$theme.'"%');
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
     * Human readable listen counter, e.g. "1 234" (Armenian thousands separator).
     */
    public function playsLabel(): string
    {
        return number_format((int) $this->plays_count, 0, ',', ' ');
    }

    /**
     * Whether the poem carries a real text (not the importer's placeholder).
     */
    public function hasRealText(): bool
    {
        return PoemText::looksReal($this->content);
    }

    /**
     * Disk that holds the recordings (outside the web root by default).
     */
    public function audioDisk(): string
    {
        return (string) config('poems.audio.disk', 'audio');
    }

    /**
     * Determine whether an audio file is available on disk.
     *
     * The private disk is authoritative; the legacy "public" location is still
     * accepted so recordings copied before the hardening keep playing.
     */
    public function hasAudio(): bool
    {
        return $this->audioFilePath() !== null;
    }

    /**
     * Absolute path of the active recording, or null when the file is missing.
     */
    public function audioFilePath(): ?string
    {
        if (blank($this->audio_path)) {
            return null;
        }

        $path = (string) $this->audio_path;

        foreach (array_unique([$this->audioDisk(), 'public']) as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->path($path);
            }
        }

        return null;
    }

    /**
     * Remove the recording from every disk it may live on (private + legacy).
     */
    public function deleteAudioFile(): void
    {
        if (blank($this->audio_path)) {
            return;
        }

        foreach (array_unique(['public', $this->audioDisk()]) as $disk) {
            if (Storage::disk($disk)->exists((string) $this->audio_path)) {
                Storage::disk($disk)->delete((string) $this->audio_path);
            }
        }
    }

    /**
     * Signed, expiring URL of the streaming route.
     *
     * The file itself is never exposed: this URL dies after
     * "poems.audio.url_ttl" seconds and (when enabled) only works in the
     * browser that rendered the page, so copying it elsewhere is useless.
     */
    public function audioUrl(): ?string
    {
        if (blank($this->getRouteKey()) || $this->audioFilePath() === null) {
            return null;
        }

        return URL::temporarySignedRoute(
            'poems.audio',
            now()->addSeconds(max(60, (int) config('poems.audio.url_ttl', 21600))),
            ['poem' => $this->getRouteKey()] + $this->audioSignatureParams(),
        );
    }

    /**
     * Extra parameters covered by the audio URL signature.
     *
     * @return array<string, string>
     */
    public function audioSignatureParams(): array
    {
        if (! config('poems.audio.bind_user_agent', true)) {
            return [];
        }

        return ['u' => static::visitorHash()];
    }

    /**
     * Stable, non-reversible hash of the browser that asks for the stream.
     */
    public static function visitorHash(?string $userAgent = null): string
    {
        $userAgent ??= (string) request()->userAgent();

        return substr(hash_hmac('sha256', $userAgent, (string) config('app.key')), 0, 16);
    }

    /**
     * Human readable source of the current voice track: the author's own
     * recording, or the one generated for the archive.
     */
    public function voiceSourceLabel(): string
    {
        return $this->is_original ? 'Օրիգինալ' : 'AI';
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
