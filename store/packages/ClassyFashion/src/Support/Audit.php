<?php

namespace ClassyFashion\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/**
 * Single place to record audit entries (report 10.2, 9.3).
 *
 * Every entry stores who (staff admin or system), what and when.
 * Reads: Spatie Activity model, e.g. Activity::where('log_name', 'classy-fashion')->latest()->get().
 */
class Audit
{
    public static function log(
        Model $subject,
        string $description,
        array $properties = [],
        ?Model $causer = null,
        string $event = 'updated'
    ): ?Activity {
        $causer ??= auth('admin')->user();

        $logger = activity('classy-fashion')
            ->performedOn($subject)
            ->event($event)
            ->withProperties($properties);

        if ($causer) {
            $logger->causedBy($causer);
        } else {
            $logger->causedByAnonymous();
            $logger->withProperty('actor', 'system');
        }

        return $logger->log($description);
    }
}
