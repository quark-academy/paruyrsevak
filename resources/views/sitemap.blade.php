<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ \App\Support\Seo::canonical() }}</loc>
        @if ($lastModified)
            <lastmod>{{ $lastModified }}</lastmod>
        @endif
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>

    @foreach ($themes as $themeKey => $themeLabel)
        <url>
            <loc>{{ \App\Support\Seo::canonical() }}?theme={{ $themeKey }}</loc>
            @if ($lastModified)
                <lastmod>{{ $lastModified }}</lastmod>
            @endif
            <changefreq>weekly</changefreq>
            <priority>0.7</priority>
        </url>
    @endforeach

    <url>
        <loc>{{ $termsUrl }}</loc>
        <changefreq>yearly</changefreq>
        <priority>0.3</priority>
    </url>

    @foreach ($poems as $poem)
        <url>
            <loc>{{ \App\Support\Seo::forPoem($poem) }}</loc>
            <lastmod>{{ $poem->updated_at?->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach
</urlset>
