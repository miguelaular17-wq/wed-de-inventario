<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

class BusinessDate
{
    public static function isPlausible(?DateTimeInterface $date, ?Carbon $today = null): bool
    {
        if ($date === null) {
            return false;
        }

        $today = ($today ?? now())->startOfDay();
        $value = Carbon::parse($date)->startOfDay();

        return $value->year >= 1990 && $value->lte($today->copy()->addDay());
    }

    public static function parseOrNull(mixed $value, ?Carbon $today = null): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $parsed = $value instanceof DateTimeInterface
                ? Carbon::instance(\DateTime::createFromInterface($value))
                : Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }

        return self::isPlausible($parsed, $today) ? $parsed : null;
    }
}
