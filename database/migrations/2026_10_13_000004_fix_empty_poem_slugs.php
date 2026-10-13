<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repairs poems whose slug is empty/NULL.
 *
 * A poem without a slug cannot be routed (and made URL generation throw
 * "Missing required parameter ... {poem}"), so we give such rows a stable slug.
 */
return new class extends Migration
{
    public function up(): void
    {
        $broken = DB::table('poems')
            ->whereNull('slug')
            ->orWhere('slug', '')
            ->orderBy('id')
            ->get(['id']);

        foreach ($broken as $row) {
            DB::table('poems')->where('id', $row->id)->update([
                'slug' => 'poem-'.$row->id,
            ]);
        }
    }

    public function down(): void
    {
        // Nothing to roll back: repaired slugs stay in place.
    }
};
