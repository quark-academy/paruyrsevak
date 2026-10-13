<?php

namespace App\Http\Controllers;

use App\Models\Poem;
use App\Models\PoemWord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PoemController extends Controller
{
    /**
     * Public, mobile-first catalogue of poems.
     */
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q', ''));
        $source = (string) $request->query('source', '');
        $sort = (string) $request->query('sort', 'new');

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
            'listenPoem' => (clone $filtered)
                ->whereNotNull('audio_path')
                ->orderByDesc('plays_count')
                ->orderBy('id')
                ->first()
                ?? Poem::query()->orderBy('id')->first(),
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
     * Aggregate numbers shown in the hero of the catalogue.
     *
     * @return array{poems: int, recordings: int, plays: int, words: int}
     */
    protected function stats(): array
    {
        return [
            'poems' => Poem::query()->count(),
            'recordings' => Poem::query()->whereNotNull('audio_path')->count(),
            'plays' => (int) Poem::query()->sum('plays_count'),
            'words' => PoemWord::query()->count(),
        ];
    }

    /**
     * The most listened poems — the counter's payoff on the home page.
     *
     * @return Collection<int, Poem>
     */
    protected function spotlight(): Collection
    {
        return Poem::query()
            ->withCount('words')
            ->where('plays_count', '>', 0)
            ->orderByDesc('plays_count')
            ->orderBy('id')
            ->take(5)
            ->get();
    }
}
