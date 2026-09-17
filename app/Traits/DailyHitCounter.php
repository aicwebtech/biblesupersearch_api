<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Atomic daily quota accounting shared by IpAccess and ApiKey.
 *
 * The previous read-then-write sequence (firstOrNew -> count++ -> save) let
 * concurrent requests overwrite each other's increments, so a client could
 * exceed the configured daily limit by issuing requests in parallel. Both the
 * row creation and the increment below are performed by the database:
 *
 *  - creation relies on the existing unique indexes
 *    (ux_ip_access_log_ip_id_date, ux_api_key_access_log_key_id_date), so a
 *    concurrent insert is ignored rather than duplicated;
 *  - the increment is a single conditional UPDATE, and the affected-row count
 *    decides whether this request was within quota.
 */
trait DailyHitCounter
{
    /**
     * Record one hit against the given daily log row.
     *
     * @param  string  $log_class  Eloquent log model (IpAccessLog / ApiKeyAccessLog)
     * @param  array   $keys       Row identity, e.g. ['ip_id' => 1, 'date' => '2026-09-05']
     * @param  int     $limit      Daily limit; 0 or less means unlimited
     * @return bool                FALSE when the request exceeds the quota
     */
    protected function incrementDailyHitsAtomic($log_class, array $keys, $limit)
    {
        $Log = new $log_class();
        $table = $Log->getTable();
        $now = $Log->freshTimestampString();

        // Create the row if it is not there yet. A racing insert loses harmlessly
        // to the unique index instead of creating a second row for the same day.
        $log_class::insertOrIgnore($keys + [
            'count'         => 0,
            'limit_reached' => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        $query = DB::table($table)->where($keys);

        if($limit > 0) {
            // Only increment while still under the limit. If no row matches, the
            // quota is already spent and this request is refused.
            $query->where('count', '<', $limit);
        }

        $affected = $query->increment('count', 1, ['updated_at' => $now]);

        if(!$affected) {
            return FALSE;
        }

        // Cached marker only; the conditional update above is the real gate.
        // Written separately so it does not depend on whether the database
        // evaluates SET expressions against pre- or post-update column values.
        //
        // Cleared as well as set: raising bss.daily_access_limit (or a key's
        // access level) part way through the day must not leave
        // isLimitReached() reporting a block that is no longer enforced.
        if($limit > 0) {
            $count_column = DB::connection()->getQueryGrammar()->wrap('count');

            DB::table($table)->where($keys)->update([
                'limit_reached' => DB::raw('CASE WHEN ' . $count_column . ' >= ' . (int) $limit . ' THEN 1 ELSE 0 END'),
                'updated_at'    => $now,
            ]);
        }

        return TRUE;
    }

    /**
     * Record one hit against a tracking row that has no quota attached.
     *
     * Same race as the quota counters, different consequence: firstOrNew -> count++ ->
     * save() lets two concurrent requests both find no row, both insert, and the loser
     * hit the unique index. That surfaced as a 500 *after* the request's quota had
     * already been spent -- the caller was charged for an access it never received.
     *
     * insertOrIgnore lets the unique index absorb the racing insert, and the increment is
     * a single UPDATE, so neither path can raise.
     *
     * @param  string  $model_class  Eloquent model for the tracking table
     * @param  array   $keys         Row identity, e.g. ['key_id' => 1, 'ip_id' => 2, 'date' => '...']
     * @return void
     */
    protected function incrementTrackingCountAtomic($model_class, array $keys)
    {
        $Model = new $model_class();
        $now = $Model->freshTimestampString();

        $model_class::insertOrIgnore($keys + [
            'count'      => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table($Model->getTable())->where($keys)->increment('count', 1, ['updated_at' => $now]);
    }
}
