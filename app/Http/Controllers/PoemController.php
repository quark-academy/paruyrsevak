<?php

namespace App\Http\Controllers;

use App\Models\Poem;
use App\Models\PoemWord;
use App\Support\Query;
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

        $source = in_array($source, ['original', 'ai'], true) ? $source : '';
        $sort = in_array($sort, ['new', 'popular'], true) ? $sort : 'new';

        $filtered = Poem::query()
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('title', 'like', "%{$term}%")
                        ->orWhere('content', 'like', "%{$term}%");
                });
            })
            ->when($source !== '', fn ($query) => $query->where('is_original', $source === 'original'));

        $poems = (clone $filtered)
            ->withCount('words')
            ->when(
                $sort === 'popular',
                fn ($query) => $query->orderByDesc('plays_count')->orderByDesc('id'),
                fn ($query) => $query->latest('id'),
            )
            ->paginate(12)
            ->withQueryString();

        $isFiltered = $term !== '' || $source !== '';

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
            'isFiltered' => $isFiltered,
            'stats' => $this->stats(),
            // Featured poems for the hero: only meaningful once listens exist.
            'spotlight' => $this->spotlight(),
            // "Start listening": the most listened recording, else the newest poem.
            'listenPoem' => $this->listenPoem($filtered),
            'moreUrl' => $moreUrl,
        ]);
    }

    /**
     * Reading page with the karaoke player.
     */
    public function show(Poem $poem): View
    {
        $poem->load('words');

        return view('poems.show', ['poem' => $poem]);
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
