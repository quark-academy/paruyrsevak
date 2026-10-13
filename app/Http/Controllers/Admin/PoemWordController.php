<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveTimingsRequest;
use App\Models\Poem;
use App\Services\PoemWordSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PoemWordController extends Controller
{
    public function __construct(protected PoemWordSynchronizer $wordSynchronizer) {}

    /**
     * Word-by-word karaoke timing editor.
     */
    public function edit(Poem $poem): View
    {
        // Words are generated from the text, so a placeholder text (imported
        // archive recording without lyrics yet) must not create word rows.
        if ($poem->words()->count() === 0 && $poem->hasRealText()) {
            $this->wordSynchronizer->syncFromContent($poem);
        }

        $poem->load('words');

        return view('admin.poems.timings', [
            'poem' => $poem,
            'words' => $poem->words,
        ]);
    }

    /**
     * Regenerate the word list from the poem content, keeping timings that
     * match by position.
     */
    public function sync(Poem $poem): RedirectResponse
    {
        $count = $this->wordSynchronizer->syncFromContent($poem);

        return back()->with('status', "Բառերը վերագեներացվեցին․ {$count} բառ։");
    }

    /**
     * Persist the timings submitted by the editor.
     */
    public function update(SaveTimingsRequest $request, Poem $poem): RedirectResponse
    {
        $payload = [];

        foreach ($request->validated()['words'] as $row) {
            if (! empty($row['delete'])) {
                continue;
            }

            $start = round((float) ($row['start_time'] ?? 0), 2);
            $end = round((float) ($row['end_time'] ?? 0), 2);

            $payload[] = [
                'word' => trim((string) $row['word']),
                'start' => $start,
                'end' => max($start, $end),
            ];
        }

        $count = $this->wordSynchronizer->persist($poem, $payload);

        return back()->with('status', "Ժամանակացույցը պահպանվեց․ {$count} բառ։");
    }
}
