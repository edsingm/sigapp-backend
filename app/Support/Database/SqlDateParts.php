<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;

final class SqlDateParts
{
    public static function year(string $expression): string
    {
        if (self::driver() === 'pgsql') {
            return "CAST(EXTRACT(YEAR FROM {$expression}) AS INTEGER)";
        }

        return "YEAR({$expression})";
    }

    public static function month(string $expression): string
    {
        if (self::driver() === 'pgsql') {
            return "CAST(EXTRACT(MONTH FROM {$expression}) AS INTEGER)";
        }

        return "MONTH({$expression})";
    }

    public static function yearAs(string $expression, string $alias): string
    {
        return self::year($expression)." as {$alias}";
    }

    public static function monthAs(string $expression, string $alias): string
    {
        return self::month($expression)." as {$alias}";
    }

    private static function driver(): string
    {
        return DB::connection()->getDriverName();
    }
}
