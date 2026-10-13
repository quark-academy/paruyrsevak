<?php

namespace App\Http\Controllers;

use App\Models\Poem;
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

        $poems = Poem::query()
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($nested) use ($term): void {
                    $nested->where('title', 'like', "%{$term}%")
                        ->orWhere('content', 'like', "%{$term}%");
                });
            })
            ->withCount('words')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        return view('poems.index', [
            'poems' => $poems,
            'term' => $term,
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
}
