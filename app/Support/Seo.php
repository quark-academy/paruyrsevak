<?php

namespace App\Support;

use App\Models\Poem;
use Illuminate\Support\Facades\Route;

/**
 * Central SEO helper: canonical URLs, titles, descriptions and structured data.
 *
 * Everything on a public page is generated from the poem and the page itself —
 * nothing has to be typed by the editor. The one promise of the site (the
 * poems are read by the author) is placed in the title when it fits and in the
 * description when the title is already at its limit.
 */
class Seo
{
    /** The promise of the site: shown in titles, or in descriptions when the title is full. */
    public const READING_PHRASE = 'Կարդում է հեղինակը';

    public const POET = 'Պարույր Սևակ';

    public const LANGUAGE = 'hy';

    public const OG_LOCALE = 'hy_AM';

    /**
     * Configured canonical base domain without the trailing slash.
     */
    public static function base(): string
    {
        $base = (string) config('poems.canonical_base');

        return $base !== '' ? rtrim($base, '/') : rtrim((string) config('app.url'), '/');
    }

    /**
     * Build a canonical URL for a path relative to the base domain.
     */
    public static function canonical(?string $path = null): string
    {
        $path = trim((string) $path, '/');

        return $path === '' ? self::base().'/' : self::base().'/'.$path;
    }

    /**
     * Canonical URL of a poem: https://subdomain.domain.am/{slug}
     */
    public static function forPoem(Poem $poem): string
    {
        return self::canonical($poem->slug);
    }

    /**
     * Best public URL for internal links: the short "/{slug}" route when it is
     * enabled, otherwise the classic "/poems/{slug}" reading page.
     */
    public static function poemUrl(Poem $poem, bool $absolute = true): string
    {
        // A poem without a route key (empty slug) cannot be linked to; never let
        // url generation throw in that case.
        if (blank($poem->getRouteKey())) {
            return route('home', [], $absolute);
        }

        if (Route::has('poems.short')) {
            return route('poems.short', ['poem' => $poem], $absolute);
        }

        return route('poems.show', ['poem' => $poem], $absolute);
    }

    /**
     * Best title for a poem (meta_title falls back to the plain title).
     */
    public static function titleForPoem(Poem $poem): string
    {
        return (string) (filled($poem->meta_title) ? $poem->meta_title : $poem->title);
    }

    /**
     * Best description for a poem.
     */
    public static function descriptionForPoem(Poem $poem, int $limit = 155): string
    {
        if (filled($poem->meta_description)) {
            return (string) $poem->meta_description;
        }

        return $poem->excerpt($limit);
    }

    /**
     * Site default description used on catalogue pages.
     */
    public static function defaultDescription(): string
    {
        return 'Պարույր Սևակի բանաստեղծությունները հեղինակի կատարմամբ՝ կարաոկե համաժամեցմամբ։';
    }

    /** How many characters a <title> may carry. */
    public static function titleLimit(): int
    {
        return max(20, (int) config('poems.seo.title_limit', 60));
    }

    /** How many characters a meta description may carry. */
    public static function descriptionLimit(): int
    {
        return max(60, (int) config('poems.seo.description_limit', 160));
    }

    /** Collapse whitespace and strip control characters. */
    public static function clean(?string $value): string
    {
        $value = (string) $value;
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    /**
     * Cut a text at a word boundary and mark the cut with an ellipsis.
     */
    public static function truncate(string $value, int $limit): string
    {
        $value = self::clean($value);

        if ($limit <= 0 || mb_strlen($value) <= $limit) {
            return $value;
        }

        $cut = mb_substr($value, 0, max(1, $limit - 1));
        $space = mb_strrpos($cut, ' ');

        // Only break on a space when it does not throw away half the text.
        if ($space !== false && $space >= (int) ($limit * 0.6)) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " \t\n\r\0\x0B.,;:—–-·").'…';
    }

    /**
     * The one image used for social previews and structured data.
     *
     * @return array{url: string, alt: string, width: int, height: int}
     */
    public static function image(): array
    {
        // Built on the canonical base (not on the request) so structured data
        // and the canonical URL always name the same origin.
        return [
            'url' => self::base().'/images/sevak-portrait.png',
            'alt' => 'Պարույր Սևակի դիմանկարը',
            'width' => 384,
            'height' => 384,
        ];
    }

    /**
     * The poem's core title, without the reading phrase and without the legacy
     * "— Բանաստեղծություն" tail that older records carry.
     */
    public static function poemTitleCore(Poem $poem): string
    {
        $stored = self::clean((string) $poem->meta_title);
        $title = self::clean((string) $poem->title);

        // The stored SEO title is an override; a title that merely repeats the
        // poem's name is not one, so the author is added automatically.
        $core = ($stored !== '' && $stored !== $title)
            ? $stored
            : self::POET.' «'.$title.'»';

        if ($title === '' && $stored === '') {
            $core = self::POET;
        }

        $core = preg_replace('/\s*[—–-]\s*(Բանաստեղծություն|Poem|Poetry)\s*$/u', '', $core) ?? $core;
        $core = self::clean($core);

        // Older records (and the importer) put the author last: "«X» — Պարույր
        // Սևակ", "X | Պարույր Սևակ". Rewriting them the usual way keeps the
        // title short enough for the reading phrase to stay in it.
        if (preg_match('/^(.*?)\s*[—–|]\s*'.preg_quote(self::POET, '/').'$/u', $core, $matches) === 1) {
            // Quotes are stripped with a regex, not trim(): trim() works on
            // bytes and would cut the last Armenian letter in half.
            $name = preg_replace('/^[«»“”"\'\s]+|[«»“”"\'\s]+$/u', '', self::clean($matches[1])) ?? '';

            if ($name !== '') {
                $core = self::POET.' «'.$name.'»';
            }
        }

        if ($core === '') {
            $core = self::POET;
        }

        // Too long for a title: keep the author and shorten the poem's name
        // inside the quotes, never in the middle of the author's name. A free
        // form override that is simply too long is cut as plain text.
        if (mb_strlen($core) > self::titleLimit()) {
            if (str_contains($core, '«')) {
                $room = self::titleLimit() - mb_strlen(self::POET) - 4; // «space» quotes
                $core = self::POET.' «'.self::truncate($title, max(12, $room)).'»';
            } else {
                $core = self::truncate($core, self::titleLimit());
            }
        }

        return $core;
    }

    /**
     * Build the complete meta payload of a page.
     *
     * Supported keys: title (core, without the reading phrase), description,
     * canonical, reading (bool), robots, keywords, type, image, article, schema
     * (extra JSON-LD nodes), searchAction (bool).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function page(array $data): array
    {
        $core = self::clean((string) ($data['title'] ?? config('app.name')));
        $core = $core !== '' ? $core : (string) config('app.name');
        $reading = (bool) ($data['reading'] ?? false);

        [$title, $readingInTitle] = self::composeTitle($core, $reading);

        $description = self::clean((string) ($data['description'] ?? ''));
        $description = $description !== '' ? $description : self::defaultDescription();

        if ($reading && ! $readingInTitle && ! self::mentionsReading($description)) {
            $description = self::READING_PHRASE.'։ '.$description;
        }

        $description = self::truncate($description, self::descriptionLimit());

        $canonical = (string) ($data['canonical'] ?? self::canonical());

        $keywords = $data['keywords'] ?? [];
        $keywords = is_array($keywords) ? $keywords : explode(',', (string) $keywords);
        $keywords = array_values(array_unique(array_filter(array_map(
            static fn ($keyword): string => self::clean((string) $keyword),
            $keywords,
        ))));

        $image = $data['image'] ?? self::image();

        if (is_string($image)) {
            $image = ['url' => $image, 'alt' => ''];
        }

        if (! is_array($image) || blank($image['url'] ?? null)) {
            $image = null;
        } else {
            $image = [
                'url' => (string) $image['url'],
                'alt' => self::clean((string) ($image['alt'] ?? '')),
                'width' => (int) ($image['width'] ?? 0),
                'height' => (int) ($image['height'] ?? 0),
            ];
        }

        $article = is_array($data['article'] ?? null) ? $data['article'] : null;
        $type = (string) ($data['type'] ?? ($article !== null ? 'article' : 'website'));

        $robots = (string) ($data['robots'] ?? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');

        // Social previews may carry a little more text than a search result.
        $ogTitle = $reading
            ? self::truncate(self::joinTitle($core, self::READING_PHRASE), self::descriptionLimit())
            : $title;

        $graph = self::graph(
            is_array($data['schema'] ?? null) ? $data['schema'] : [],
            (bool) ($data['searchAction'] ?? false),
        );

        return [
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
            'keywords' => implode(', ', $keywords),
            'image' => $image,
            'type' => $type,
            'locale' => self::OG_LOCALE,
            'language' => self::LANGUAGE,
            'og_title' => $ogTitle,
            'og_description' => $description,
            'article' => $article,
            'reading_in_title' => $readingInTitle,
            'graph' => $graph,
            'graph_json' => self::json($graph),
        ];
    }

    /**
     * Complete meta payload of a poem page (reading page and full-screen player
     * of the same poem share it: they also share the canonical URL).
     *
     * @return array<string, mixed>
     */
    public static function poemPage(Poem $poem): array
    {
        $canonical = self::forPoem($poem);
        $themes = $poem->themeLabels();

        $description = filled($poem->meta_description)
            ? (string) $poem->meta_description
            : self::autoPoemDescription($poem, $themes);

        $keywords = array_merge(
            [self::POET, $poem->title, 'բանաստեղծություն', 'հայ պոեզիա', 'կարաոկե', 'աուդիո'],
            $themes,
        );

        $image = self::image();

        $schema = [
            self::poemSchema($poem, $canonical, $themes, $image),
            self::breadcrumbs([
                ['name' => 'Գլխավոր', 'url' => self::canonical()],
                ['name' => 'Բոլոր բանաստեղծությունները', 'url' => self::canonical('poems')],
                ['name' => (string) $poem->title, 'url' => $canonical],
            ], $canonical.'#breadcrumb'),
        ];

        return self::page([
            'title' => self::poemTitleCore($poem),
            'description' => $description,
            'canonical' => $canonical,
            // The promise of the site — the poems are voiced by their author.
            'reading' => true,
            'keywords' => $keywords,
            'image' => $image,
            'type' => 'article',
            'article' => [
                'published' => $poem->created_at?->toAtomString(),
                'modified' => $poem->updated_at?->toAtomString(),
                'section' => 'Պոեզիա',
                'tags' => $themes,
            ],
            'schema' => $schema,
        ]);
    }

    /**
     * Automatic description of a poem: what it is, how to listen to it, what it
     * is about — and the beginning of the text.
     *
     * @param  list<string>  $themes
     */
    protected static function autoPoemDescription(Poem $poem, array $themes): string
    {
        $parts = ['Լսիր Պարույր Սևակի «'.self::clean((string) $poem->title).'» բանաստեղծությունը հեղինակի կատարմամբ՝ կարաոկե համաժամեցմամբ։'];

        if ($themes !== []) {
            $parts[] = 'Թեմաներ՝ '.implode(', ', $themes).'։';
        }

        $excerpt = $poem->excerpt(120);

        if ($excerpt !== '') {
            $parts[] = $excerpt;
        }

        return self::clean(implode(' ', $parts));
    }

    /**
     * The main structured-data node of a poem.
     *
     * @param  list<string>  $themes
     * @param  array{url: string, alt: string, width: int, height: int}  $image
     * @return array<string, mixed>
     */
    protected static function poemSchema(Poem $poem, string $canonical, array $themes, array $image): array
    {
        $base = self::base();
        $title = self::clean((string) $poem->title);
        $words = preg_split('/\s+/u', trim((string) $poem->content)) ?: [];

        $node = [
            '@type' => ['Article', 'CreativeWork'],
            '@id' => $canonical.'#poem',
            'url' => $canonical,
            'mainEntityOfPage' => ['@id' => $canonical],
            'headline' => self::truncate($title, 110),
            'name' => $title,
            'description' => self::truncate(
                filled($poem->meta_description) ? (string) $poem->meta_description : $poem->excerpt(200),
                self::descriptionLimit(),
            ),
            'inLanguage' => self::LANGUAGE,
            'author' => ['@id' => $base.'/#poet'],
            'creator' => ['@id' => $base.'/#poet'],
            'publisher' => ['@id' => $base.'/#poet'],
            'isPartOf' => ['@id' => $base.'/#website'],
            'genre' => 'Պոեզիա',
            'articleSection' => 'Պոեզիա',
            'keywords' => implode(', ', array_merge([self::POET, 'բանաստեղծություն'], $themes)),
            'text' => self::truncate((string) $poem->content, 1200),
            'wordCount' => count(array_filter($words, static fn (string $word): bool => $word !== '')),
            'datePublished' => $poem->created_at?->toAtomString(),
            'dateModified' => $poem->updated_at?->toAtomString(),
            'image' => [
                '@type' => 'ImageObject',
                'url' => $image['url'],
                'caption' => $image['alt'],
            ],
            'isAccessibleForFree' => true,
            // The recording itself is streamed through a signed, expiring URL,
            // so only its description travels in the markup.
            'audio' => $poem->hasAudio() ? [
                '@type' => 'AudioObject',
                'name' => $title.' — '.self::READING_PHRASE,
                'inLanguage' => self::LANGUAGE,
                'encodingFormat' => 'audio/mpeg',
                'isAccessibleForFree' => true,
            ] : null,
        ];

        return array_filter($node, static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * A Schema.org ItemList built from a page of poems.
     *
     * @param  iterable<int, Poem>  $poems
     * @return array<string, mixed>
     */
    public static function poemItemList(iterable $poems, string $id, ?string $name = null): array
    {
        $items = [];
        $position = 0;

        foreach ($poems as $poem) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'name' => self::clean((string) $poem->title),
                'url' => self::forPoem($poem),
            ];
        }

        return array_filter([
            '@type' => 'ItemList',
            '@id' => $id,
            'name' => $name,
            'numberOfItems' => count($items),
            'itemListElement' => $items,
        ], static fn ($value): bool => $value !== null && $value !== '' && $value !== []);
    }

    /**
     * A Schema.org BreadcrumbList.
     *
     * @param  list<array{name: string, url: string}>  $trail
     * @return array<string, mixed>
     */
    public static function breadcrumbs(array $trail, string $id): array
    {
        $items = [];

        foreach (array_values($trail) as $index => $crumb) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => self::clean($crumb['name']),
                'item' => $crumb['url'],
            ];
        }

        return [
            '@type' => 'BreadcrumbList',
            '@id' => $id,
            'itemListElement' => $items,
        ];
    }

    /**
     * The site-wide graph: the website itself and the poet as a person.
     *
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<string, mixed>
     */
    protected static function graph(array $nodes, bool $searchAction): array
    {
        $base = self::base();
        $name = (string) config('app.name');

        $website = [
            '@type' => 'WebSite',
            '@id' => $base.'/#website',
            'url' => $base.'/',
            'name' => $name !== '' ? $name : self::POET,
            'alternateName' => self::POET.' — բանաստեղծությունների ձայնը',
            'inLanguage' => self::LANGUAGE,
            'publisher' => ['@id' => $base.'/#poet'],
        ];

        if ($searchAction) {
            $website['potentialAction'] = [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $base.'/?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ];
        }

        $poet = [
            '@type' => 'Person',
            '@id' => $base.'/#poet',
            'name' => self::POET,
            'url' => $base.'/',
            'jobTitle' => 'Բանաստեղծ',
            'description' => 'Հայ բանաստեղծ, «Մատյան ողբերգության» հեղինակ։',
        ];

        $sameAs = array_values(array_filter(array_map(
            static fn ($link): string => trim((string) $link),
            (array) config('poems.seo.same_as', []),
        )));

        if ($sameAs !== []) {
            $poet['sameAs'] = $sameAs;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_filter(array_merge([$website, $poet], $nodes))),
        ];
    }

    /**
     * @return array{0: string, 1: bool} the title and whether it carries the phrase
     */
    protected static function composeTitle(string $core, bool $reading): array
    {
        $limit = self::titleLimit();

        if ($reading) {
            $candidate = self::joinTitle($core, self::READING_PHRASE);

            if (mb_strlen($candidate) <= $limit) {
                return [$candidate, true];
            }
        }

        // The phrase did not fit: the title keeps the poem, the site name is
        // added when there is still room for it.
        if (! str_contains($core, self::POET)) {
            $withPoet = $core.' | '.self::POET;

            if (mb_strlen($withPoet) <= $limit) {
                return [$withPoet, false];
            }
        }

        return [self::truncate($core, $limit), false];
    }

    /**
     * Join a title with an addition, using the separator the title already uses.
     */
    protected static function joinTitle(string $core, string $addition): string
    {
        $separator = (str_contains($core, ' — ') || str_contains($core, ' – ')) ? ' · ' : ' — ';

        return $core.$separator.$addition;
    }

    protected static function mentionsReading(string $text): bool
    {
        return mb_stripos($text, self::READING_PHRASE) !== false;
    }

    /**
     * Encode structured data for an inline script: no raw "<", ">", quotes or
     * ampersands, so a poem title can never break out of the tag.
     */
    public static function json(array $data): string
    {
        return (string) json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT,
        );
    }
}
