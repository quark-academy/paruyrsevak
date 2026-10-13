<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Poem;
use App\Models\PoemWord;
use App\Models\User;
use App\Services\ElevenLabsService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(ElevenLabsService $elevenLabs): View
    {
        return view('admin.dashboard', [
            'poemsCount' => Poem::query()->count(),
            'wordsCount' => PoemWord::query()->count(),
            'originalCount' => Poem::query()->where('is_original', true)->count(),
            'aiCount' => Poem::query()->where('is_original', false)->count(),
            'adminsCount' => User::query()->where('is_admin', true)->count(),
            'elevenLabsReady' => $elevenLabs->isConfigured(),
        ]);
    }
}
