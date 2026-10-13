<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Safe access to query string values.
 *
 * A visitor (or a bot scanning links) can send "?q[]=x", which makes
 * $request->query('q') return an array. Casting that to a string raised an
 * "Array to string conversion" error and turned the page into a 500. Only
 * plain scalar values are accepted here, so a malformed link behaves exactly
 * like an empty filter.
 */
class Query
{
    public static function string(Request $request, string $key, string $default = ''): string
    {
        $value = $request->query($key);

        if (! is_string($value) && ! is_numeric($value)) {
            return $default;
        }

        return trim((string) $value);
    }
}
