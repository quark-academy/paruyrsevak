<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PoemRequest;
use App\Models\Poem;
use App\Services\ElevenLabsService;
use App\Support\Query;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PoemController extends Controller
{
    public function __construct(protected ElevenLabsService $elevenLabs) {}

    public function index(Request $request): View
    {
        $term = Query::string($request, 'q');

        $poems = Poem::query()
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('title', 'like', "%{$term}%")
                        ->orWhere('content', 'like', "%{$term}%");
                });
            })
            ->withCount('words')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.poems.index', [
            'poems' => $poems,
            'term' => $term,
        ]);
    }

    public function create(): View
    {
        return view('admin.poems.create', ['poem' => new Poem(['is_original' => false])]);
    }

    public function store(PoemRequest $request): RedirectResponse
    {
        $poem = Poem::create($request->validated());

        return redirect()
            ->route('admin.poems.show', $poem)
            ->with('status', 'Բանաստեղծությունը ստեղծվեց։');
    }

    public function show(Poem $poem): View
    {
        $poem->load('words');

        return view('admin.poems.show', [
            'poem' => $poem,
            'aiAudioReady' => $this->elevenLabs->audioExists($poem),
        ]);
    }

    public function edit(Poem $poem): View
    {
        return view('admin.poems.edit', ['poem' => $poem]);
    }

    public function update(PoemRequest $request, Poem $poem): RedirectResponse
    {
        $poem->update($request->validated());

        return redirect()
            ->route('admin.poems.show', $poem)
            ->with('status', 'Բանաստեղծությունը թարմացվեց։');
    }

    public function destroy(Poem $poem): RedirectResponse
    {
        if (filled($poem->audio_path)) {
            Storage::disk('public')->delete((string) $poem->audio_path);
        }

        $this->elevenLabs->forget($poem);
        $poem->delete();

        return redirect()
            ->route('admin.poems.index')
            ->with('status', 'Բանաստեղծությունը ջնջվեց։');
    }
}
