<?php

namespace App\Support;

use App\Models\Poem;
use Illuminate\Support\Facades\Route;

/**
 * Central SEO helper: canonical URLs and meta defaults.
 */
class Seo
{
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
        return (string) config('app.name').' — Պարույր Սևակի բանաստեղծությունները կարաոկե համաժամեցմամբ։';
    }
}
