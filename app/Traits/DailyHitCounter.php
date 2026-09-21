<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $created = $this->createDailyRowIfMissing($log_class, $table, $keys, [
            'count'         => 0,
            'limit_reached' => 0,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        // Without this, a row that could not be created is indistinguishable from a quota that
        // has been spent: the conditional UPDATE below matches nothing either way and this
        // method returns FALSE, so the client gets an unexplained 429 on every request with no
        // row and no log entry to account for it. insertOrIgnore is what makes the two look
        // alike -- INSERT IGNORE downgrades every insert error to a warning, not just the
        // duplicate key it is there for -- so the row is asked after rather than assumed.
        if(!$created) {
            Log::error('Daily hit accounting failed: no row in ' . $table . ' for ' . json_encode($keys) . ' and it could not be created. INSERT IGNORE hides the reason; check that table for an exhausted AUTO_INCREMENT id or a constraint violation.');

            // Refused rather than served: the hit cannot be accounted for, so granting it would
            // put the quota back under the read-then-write behaviour this trait replaced. No
            // marker is written, so the next request retries the insert and the refusal clears
            // itself once the table is usable again.
            return FALSE;
        }

        $query = DB::table($table)->where($keys);

        if($limit > 0) {
            // Only increment while still under the limit. If no row matches, the
            // quota is already spent and this request is refused.
            $query->where('count', '<', $limit);
        }

        $affected = $query->increment('count', 1, ['updated_at' => $now]);

        if(!$affected) {
            // Nothing matched, so the quota is spent -- and the marker has to be set
            // here as well as on the success path below. Lowering
            // bss.daily_access_limit (or an API key's access level) part way through a
            // day the count has already passed means the conditional UPDATE above stops
            // matching before it ever records the limit as reached, which left
            // isLimitReached() reporting FALSE while every request was refused.
            //
            // Guarded on the current value so the corrective UPDATE matches a row once
            // and then goes quiet, rather than rewriting the marker on every refusal.
            if($limit > 0) {
                DB::table($table)->where($keys)->where('limit_reached', 0)->update([
                    'limit_reached' => 1,
                    'updated_at'    => $now,
                ]);
            }

            return FALSE;
        }

        // Cached marker only; the conditional update above is the real gate.
        // Written separately so it does not depend on whether the database
        // evaluates SET expressions against pre- or post-update column values.
        //
        // Cleared as well as set: raising bss.daily_access_limit (or a key's
        // access level) part way through the day must not leave
        // isLimitReached() reporting a block that is no longer enforced. The
        // opposite move, lowering it, is handled on the refusal path above.
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
        $table = $Model->getTable();
        $now = $Model->freshTimestampString();

        $created = $this->createDailyRowIfMissing($model_class, $table, $keys, [
            'count'      => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Nothing here refuses the request -- this table carries no quota -- but the increment
        // below would silently count nothing, so the table going unwritable has to be said out
        // loud rather than left to be noticed in the numbers.
        if(!$created) {
            Log::error('Access tracking failed: no row in ' . $table . ' for ' . json_encode($keys) . ' and it could not be created. INSERT IGNORE hides the reason; check that table for an exhausted AUTO_INCREMENT id or a constraint violation.');

            return;
        }

        DB::table($table)->where($keys)->increment('count', 1, ['updated_at' => $now]);
    }

    /**
     * Create today's row for $keys unless it is already there.
     *
     * The presence check is not an optimisation and is not what makes this safe -- the
     * unique index is, and insertOrIgnore still absorbs an insert that races this one.
     * It is here to keep the insert off the common path, because INSERT IGNORE is not
     * free when it is ignored: InnoDB allocates an AUTO_INCREMENT value before it
     * detects the duplicate key (true of innodb_autoinc_lock_mode 1 and 2 alike), so
     * issuing one per request advances `id` per request rather than per key per day.
     * These tables carry `increments('id')`, i.e. int unsigned, and running that column
     * out of range would start failing every insert on a table that only ever appends.
     *
     * @param  string  $model_class  Eloquent model owning the table
     * @param  string  $table        Its table name, already resolved
     * The row is asked for again after an insert rather than assumed: insertOrIgnore emits
     * INSERT IGNORE, which downgrades *every* insert error to a warning - a constraint
     * violation, a truncation, the exhausted id above - and not only the duplicate key it is
     * used for here. The extra read costs one query per key per day, on the path that was
     * already inserting.
     *
     * @param  string  $model_class  Eloquent model owning the table
     * @param  string  $table        Its table name, already resolved
     * @param  array   $keys         Row identity
     * @param  array   $defaults     Column values for a newly created row
     * @return bool                  FALSE when the row is still not there afterwards
     */
    protected function createDailyRowIfMissing($model_class, $table, array $keys, array $defaults)
    {
        if(DB::table($table)->where($keys)->exists()) {
            return TRUE;
        }

        $model_class::insertOrIgnore($keys + $defaults);

        return DB::table($table)->where($keys)->exists();
    }
}
