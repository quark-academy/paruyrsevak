<?php

use App\Support\PoemThemes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the theme tags and fill them for the poems that already exist.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('poems', 'themes')) {
            Schema::table('poems', function (Blueprint $table) {
                $table->json('themes')->nullable();
            });
        }

        DB::table('poems')
            ->select(['id', 'title', 'themes'])
            ->orderBy('id')
            ->each(function (object $poem): void {
                if (filled($poem->themes)) {
                    return;
                }

                DB::table('poems')
                    ->where('id', $poem->id)
                    ->update(['themes' => json_encode(PoemThemes::forTitle((string) $poem->title))]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('poems', 'themes')) {
            Schema::table('poems', function (Blueprint $table) {
                $table->dropColumn('themes');
            });
        }
    }
};
