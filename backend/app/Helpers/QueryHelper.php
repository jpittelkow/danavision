<?php

namespace App\Helpers;

class QueryHelper
{
    /**
     * Escape LIKE metacharacters (%, _, \) in a search string.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }
}
