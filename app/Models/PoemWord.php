<?php

namespace App\Models;

use Database\Factories\PoemWordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PoemWord extends Model
{
    /** @use HasFactory<PoemWordFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'poem_id',
        'word',
        'start_time',
        'end_time',
        'word_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_time' => 'float',
            'end_time' => 'float',
            'word_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Poem, $this>
     */
    public function poem(): BelongsTo
    {
        return $this->belongsTo(Poem::class);
    }

    /**
     * Length of the word in seconds (for progress bars).
     */
    public function duration(): float
    {
        return max(0.0, $this->end_time - $this->start_time);
    }
}
