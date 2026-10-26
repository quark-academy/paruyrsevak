<?php

namespace App\Http\Controllers;

use App\Models\Poem;
use App\Models\PoemWord;
use App\Support\PoemThemes;
use App\Support\Query;
use App\Support\Seo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Throwable;

class PoemController extends Controller
{
    /**
     * Public, mobile-first catalogue of poems.
     *
     * The same action also answers the "load more" button: an asynchronous
     * request gets only the next slice of cards (and the URL of the slice after
     * it in a header), so the catalogue never sends anyone to a second page.
     */
    public function index(Request $request): View|Response
    {
        $term = Query::string($request, 'q');
        $source = Query::string($request, 'source');
        $sort = Query::string($request, 'sort', 'new');
        $theme = Query::string($request, 'theme');

        $source = in_array($source, ['original', 'ai'], true) ? $source : '';
        $sort = in_array($sort, ['new', 'popular'], true) ? $sort : 'new';
        $theme = array_key_exists($theme, PoemThemes::LABELS) ? $theme : '';

        $filtered = Poem::query()
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('title', 'like', "%{$term}%")
                        ->orWhere('content', 'like', "%{$term}%");
                });
            })
            ->when($source !== '', fn ($query) => $query->where('is_original', $source === 'original'))
            ->when($theme !== '', fn ($query) => $query->withTheme($theme));

        $poems = (clone $filtered)
            ->withCount('words')
            ->when(
                $sort === 'popular',
                fn ($query) => $query->orderByDesc('plays_count')->orderByDesc('id'),
                fn ($query) => $query->latest('id'),
            )
            ->paginate(12)
            ->withQueryString();

        $isFiltered = $term !== '' || $source !== '' || $theme !== '';

        // More of the catalogue: this very URL with the query of the next slice.
        // The trailing slash is kept on purpose — a request to the bare base
        // path is answered by the host with a redirect, and a host that builds
        // that redirect from its own view of the scheme hands back an http URL,
        // which the browser then refuses as mixed content.
        $nextQuery = array_merge($request->query(), ['page' => $poems->currentPage() + 1]);
        $moreUrl = $poems->hasMorePages()
            ? rtrim(url()->current(), '/').'/?'.http_build_query($nextQuery)
            : null;

        // The button asks this very URL, marked as a fragment: only the cards
        // come back. The flag is read from the query as well as from the ajax
        // header, because a proxy that strips the header would otherwise answer
        // with a whole page — and pasting a whole page into the list is what
        // tears the layout apart.
        if ($request->ajax() || $request->boolean('fragment')) {
            return response()
                ->view('poems._cards', ['poems' => $poems])
                ->header('X-Next-Page', $moreUrl ?? '');
        }

        return view('poems.index', [
            'poems' => $poems,
            'term' => $term,
            'source' => $source,
            'sort' => $sort,
            'theme' => $theme,
            'themes' => PoemThemes::LABELS,
            'isFiltered' => $isFiltered,
            'stats' => $this->stats(),
            // Featured poems for the hero: only meaningful once listens exist.
            'spotlight' => $this->spotlight(),
            // "Start listening": the most listened recording, else the newest poem.
            'listenPoem' => $this->listenPoem($filtered),
            'moreUrl' => $moreUrl,
            'seo' => $this->catalogueSeo($request, $poems, $term, $theme, $source, $sort),
        ]);
    }

    /**
     * Reading page with the karaoke player.
     */
    public function show(Poem $poem): View
    {
        $poem->load('words');

        return view('poems.show', [
            'poem' => $poem,
            'seo' => Seo::poemPage($poem),
        ]);
    }

    /**
     * Titles, descriptions and structured data of the catalogue.
     *
     * All of it is derived from the current filters: the theme gives the page
     * its own subject ("Սիրո բանաստեղծություններ"), a search is not indexed,
     * and the poems on the page form an ItemList for crawlers.
     *
     * @param  \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Poem>  $poems
     * @return array<string, mixed>
     */
    protected function catalogueSeo(
        Request $request,
        $poems,
        string $term,
        string $theme,
        string $source,
        string $sort,
    ): array {
        $page = (int) $poems->currentPage();
        $canonical = Seo::canonical();

        if ($term !== '') {
            $title = 'Որոնում՝ '.$term;
            $description = 'Պարույր Սևակի բանաստեղծությունների որոնման արդյունքները «'.$term.'» հարցման համար։';
            $canonical = Seo::canonical().'?q='.rawurlencode($term);
        } elseif ($theme !== '') {
            $themeTitle = PoemThemes::SEO_TITLES[$theme] ?? 'Բանաստեղծություններ';
            $title = $themeTitle.' — '.Seo::POET;
            $description = $themeTitle.'՝ Պարույր Սևակի գրիչից։ Լսիր հեղինակի կատարմամբ՝ կարաոկե համաժամեցմամբ։ Սեր, կյանք, հայրենիք, կարոտ և փիլիսոփայություն։';
            $canonical = Seo::canonical().'?theme='.$theme;
        } elseif ($source !== '') {
            $title = $source === 'original'
                ? Seo::POET.' · արխիվային ձայնագրություններ'
                : Seo::POET.' · AI ձայնով բանաստեղծություններ';
            $description = 'Պարույր Սևակի բանաստեղծությունները '.
                ($source === 'original' ? 'հեղինակի արխիվային ձայնագրություններով։' : 'AI ձայնով և կարաոկե համաժամեցմամբ։');
        } else {
            // Short and unmistakable: the site is the poet reading his poems.
            $title = Seo::POET;
            $description = Seo::defaultDescription().' Սեր, կյանք, հայրենիք, կարոտ և փիլիսոփայություն։';
        }

        // Search results and the deeper slices of the list are not worth their
        // own place in an index: they stay crawlable, but not indexed.
        $robots = ($term !== '' || $page > 1)
            ? 'noindex, follow, max-image-preview:large'
            : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

        $image = Seo::image();
        $listName = $term !== '' ? 'Որոնման արդյունքներ' : 'Բանաստեղծություններ';
        $collectionName = $term !== ''
            ? 'Որոնում՝ '.$term
            : ($theme !== '' ? (PoemThemes::SEO_TITLES[$theme] ?? 'Բանաստեղծություններ') : Seo::POET.' — բանաստեղծությունների ձայնը');

        return Seo::page([
            'title' => $title,
            'description' => $description,
            'canonical' => $canonical,
            'robots' => $robots,
            // The catalogue promises the author's performance only on the
            // unfiltered page; a "source=ai" slice must not claim it.
            'reading' => $source !== 'ai' && $term === '',
            'keywords' => array_merge(
                [Seo::POET, 'բանաստեղծություն', 'հայ պոեզիա', 'կարաոկե', 'աուդիո'],
                array_values(PoemThemes::LABELS),
            ),
            'image' => $image,
            'type' => 'website',
            // The sitelinks search box belongs to the plain home page only.
            'searchAction' => $page === 1 && $term === '' && $theme === '' && $source === '' && $sort === 'new',
            'schema' => [
                array_filter([
                    '@type' => 'CollectionPage',
                    '@id' => $canonical.'#collection',
                    'url' => $canonical,
                    'name' => $collectionName,
                    'description' => $description,
                    'inLanguage' => Seo::LANGUAGE,
                    'isPartOf' => ['@id' => Seo::base().'/#website'],
                    'mainEntity' => Seo::poemItemList($poems->getCollection(), $canonical.'#poems', $listName),
                ]),
                Seo::breadcrumbs([
                    ['name' => 'Գլխավոր', 'url' => Seo::canonical()],
                    ['name' => 'Բոլոր բանաստեղծությունները', 'url' => Seo::canonical('poems')],
                ], $canonical.'#breadcrumb'),
            ],
        ]);
    }

    /**
     * Poem offered by the hero's "start listening" button.
     */
    protected function listenPoem(Builder $filtered): ?Poem
    {
        try {
            return (clone $filtered)
                ->whereNotNull('audio_path')
                ->orderByDesc('plays_count')
                ->orderBy('id')
                ->first()
                ?? Poem::query()->orderBy('id')->first();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * Aggregate numbers shown in the hero of the catalogue.
     *
     * The counter is decoration: an unmigrated host (missing plays_count) or a
     * broken cache must never take the whole catalogue down, so a failure is
     * reported to the log and the page keeps rendering.
     *
     * @return array{poems: int, recordings: int, plays: int, words: int}
     */
    protected function stats(): array
    {
        try {
            return [
                'poems' => Poem::query()->count(),
                'recordings' => Poem::query()->whereNotNull('audio_path')->count(),
                'plays' => (int) Poem::query()->sum('plays_count'),
                'words' => PoemWord::query()->count(),
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['poems' => 0, 'recordings' => 0, 'plays' => 0, 'words' => 0];
        }
    }

    /**
     * The most listened poems — the counter's payoff on the home page.
     *
     * @return Collection<int, Poem>
     */
    protected function spotlight(): Collection
    {
        try {
            return Poem::query()
                ->withCount('words')
                ->where('plays_count', '>', 0)
                ->orderByDesc('plays_count')
                ->orderBy('id')
                ->take(5)
                ->get();
        } catch (Throwable $exception) {
            report($exception);

            return new Collection;
        }
    }
}
