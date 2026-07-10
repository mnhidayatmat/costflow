<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class Search
{
    /**
     * `!` rather than the usual backslash: MySQL applies its own backslash
     * processing to string literals while SQLite does not, so `ESCAPE '\'`
     * cannot be written once for both. `!` is a single character to each.
     */
    private const ESCAPE = '!';

    /**
     * Wrap a user's search term for LIKE, neutering any wildcards they typed.
     */
    public static function term(string $term): string
    {
        $escaped = str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $term
        );

        return '%'.$escaped.'%';
    }

    /**
     * OR together a LIKE across several columns.
     *
     * Column names are supplied by the caller, never by the request, so they
     * are safe to interpolate; the term itself is always bound.
     *
     * @param  list<string>  $columns
     */
    public static function across(Builder $query, array $columns, string $term): Builder
    {
        $like = self::term($term);

        return $query->where(function (Builder $q) use ($columns, $like) {
            foreach ($columns as $column) {
                $q->orWhereRaw("{$column} LIKE ? ESCAPE '".self::ESCAPE."'", [$like]);
            }
        });
    }
}
